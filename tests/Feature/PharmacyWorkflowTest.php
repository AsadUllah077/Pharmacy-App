<?php

namespace Tests\Feature;

use App\Jobs\RefreshInventoryAlerts;
use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Checkout;
use App\Services\Expenses;
use App\Services\Ledger;
use App\Services\Purchasing;
use App\Services\ReturnSale;
use App\Support\Money;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PharmacyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private int $warehouse;

    private Medicine $medicine;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
        $this->seed(ReferenceSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('name', 'Super Administrator')->first());
        $this->branch = Branch::create(['name' => 'Test central', 'code' => 'TEST']);
        $this->admin->branches()->attach($this->branch);
        $this->warehouse = $this->branch->warehouses()->create(['name' => 'Dispensary'])->id;
        $this->supplier = Supplier::create(['name' => 'Fictional supplier']);
        $this->medicine = Medicine::create(['name' => 'Test medicine', 'sku' => 'MED1', 'selling_price' => '10.00', 'tax_basis_points' => 500]);
    }

    private function receive(int $quantity = 10, string $expiry = '2027-04-01', string $cost = '4.00'): MedicineBatch
    {
        $service = app(Purchasing::class);
        $order = $service->order($this->admin, $this->branch->id, ['supplier_id' => $this->supplier->id, 'items' => [['medicine_id' => $this->medicine->id, 'quantity' => $quantity, 'unit_cost' => $cost]]]);
        $receipt = $service->receive($this->admin, $order, ['warehouse_id' => $this->warehouse, 'request_key' => (string) Str::uuid(), 'items' => [['item_id' => $order->items()->first()->id, 'quantity' => $quantity, 'lot_number' => 'B-'.Str::random(8), 'expires_at' => $expiry]]]);

        return $receipt->batches()->first();
    }

    private function cart(int $quantity = 2): array
    {
        return ['warehouse_id' => $this->warehouse, 'request_key' => (string) Str::uuid(), 'items' => [['medicine_id' => $this->medicine->id, 'quantity' => $quantity, 'unit_discount' => '0.00']], 'payments' => [['method' => 'cash', 'amount' => Money::decimal(1050 * $quantity)]]];
    }

    private function checkout(array $cart = []): Sale
    {
        return app(Checkout::class)->execute($this->admin, $this->branch->id, $cart ?: $this->cart());
    }

    private function refund(Sale $sale, int $quantity = 1, array $extra = [])
    {
        return app(ReturnSale::class)->execute($this->admin, $sale, [...['request_key' => (string) Str::uuid(), 'reason' => 'Storage integrity not confirmed', 'items' => [['allocation_id' => $sale->items()->first()->allocations()->first()->id, 'quantity' => $quantity]]], ...$extra]);
    }

    private function assertBalanced(): void
    {
        foreach (DB::table('journal_entries')->get() as $entry) {
            $lines = DB::table('journal_lines')->where('journal_entry_id', $entry->id);
            $this->assertSame(Money::minor((string) (clone $lines)->sum('debit')), Money::minor((string) (clone $lines)->sum('credit')));
        }
    }

    public function test_receive_sell_return_pay_and_expense_reconcile(): void
    {
        $batch = $this->receive();
        $sale = $this->checkout();
        $this->assertSame('21.00', $sale->total);
        $this->assertSame(8, $batch->fresh()->quantity);
        $this->assertDatabaseHas('payments', ['sale_id' => $sale->id, 'method' => 'cash', 'amount' => '21.00']);
        $return = $this->refund($sale);
        $this->assertSame('10.50', $return->total);
        $this->assertSame(8, $batch->fresh()->quantity);
        $this->assertSame(1, $batch->fresh()->quarantined_quantity);
        app(Purchasing::class)->pay($this->admin, $this->branch->id, ['supplier_id' => $this->supplier->id, 'amount' => '20.00', 'method' => 'bank', 'request_key' => (string) Str::uuid()]);
        app(Expenses::class)->pay($this->admin, $this->branch->id, ['amount' => '3.00', 'method' => 'cash', 'category' => 'Utilities', 'description' => 'Electricity', 'request_key' => (string) Str::uuid()]);
        $this->assertBalanced();
        $inventory = DB::table('journal_lines')->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')->where('code', '1200')->selectRaw('SUM(debit)-SUM(credit) as net')->value('net');
        $this->assertSame(3600, Money::minor((string) $inventory));
        $this->assertSame(8, (int) $batch->movements()->sum('quantity_delta'));
        $this->assertSame(1, (int) $batch->movements()->sum('quarantine_delta'));
        $this->assertDatabaseCount('refunds', 1);
        $this->assertDatabaseCount('audit_logs', 6);
    }

    public function test_fefo_uses_multiple_batches_and_excludes_expired_and_quarantined(): void
    {
        $later = $this->receive(4, '2027-08-01', '5.00');
        $early = $this->receive(2, '2026-12-01', '3.00');
        $expired = $this->receive(5, '2026-10-14');
        $quarantine = $this->receive(5);
        $quarantine->update(['status' => 'quarantined']);
        $sale = $this->checkout($this->cart(3));
        $this->assertSame(0, $early->fresh()->quantity);
        $this->assertSame(3, $later->fresh()->quantity);
        $this->assertSame(5, $expired->fresh()->quantity);
        $this->assertSame(5, $quarantine->fresh()->quantity);
        $this->assertSame('11.00', $sale->cost);
        $this->assertDatabaseCount('sale_allocations', 2);
        $this->assertBalanced();
    }

    public function test_insufficient_stock_rolls_back_every_effect_including_invoice_sequence(): void
    {
        $batch = $this->receive(1);
        $before = DB::table('journal_entries')->count();
        try {
            $this->checkout($this->cart(2));
            $this->fail('Expected insufficient stock.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items', $e->errors());
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('journal_entries', $before);
        $this->assertSame(1, $batch->fresh()->quantity);
        $this->assertSame(0, $this->branch->fresh()->invoice_sequence);
    }

    public function test_failed_payment_rolls_back_stock_and_sale(): void
    {
        $batch = $this->receive();
        $data = $this->cart();
        $data['payments'][0]['amount'] = '1.00';
        try {
            $this->checkout($data);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payments', $e->errors());
        }
        $this->assertSame(10, $batch->fresh()->quantity);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_duplicate_checkout_returns_original_sale_and_rejects_changed_payload(): void
    {
        $batch = $this->receive();
        $data = $this->cart();
        $sale = $this->checkout($data);
        $again = $this->checkout($data);
        $this->assertSame($sale->id, $again->id);
        $this->assertSame(8, $batch->fresh()->quantity);
        $this->assertDatabaseCount('sales', 1);
        $data['items'][0]['quantity'] = 1;
        $this->expectException(HttpException::class);
        $this->checkout($data);
    }

    public function test_split_payment_change_discount_and_tax_are_server_calculated(): void
    {
        $this->receive();
        $data = $this->cart();
        $data['items'][0]['unit_discount'] = '1.00';
        $data['items'][0]['price'] = '0.01';
        $data['total'] = '0.01';
        $data['payments'] = [['method' => 'card', 'amount' => '10.00'], ['method' => 'cash', 'amount' => '10.00']];
        $sale = $this->checkout($data);
        $this->assertSame('18.90', $sale->total);
        $this->assertSame('1.10', $sale->change);
        $this->assertSame('0.90', $sale->tax);
        $this->assertSame('2.00', $sale->discount);
        $this->assertEquals(18.90, $sale->payments()->sum('amount'));
        $this->assertBalanced();
    }

    public function test_cashier_cannot_discount_or_refund_or_access_finance(): void
    {
        $this->receive();
        $cashier = User::factory()->create();
        $cashier->roles()->attach(Role::where('name', 'Cashier')->first());
        $cashier->branches()->attach($this->branch);
        $this->actingAs($cashier)->withSession(['branch_id' => $this->branch->id])->get('/accounting')->assertForbidden();
        $data = $this->cart();
        $data['items'][0]['unit_discount'] = '1.00';
        $this->post('/checkout', $data)->assertForbidden();
        $this->assertDatabaseCount('sales', 0);
        $sale = $this->checkout();
        $this->post('/sales/'.$sale->id.'/returns', ['request_key' => (string) Str::uuid(), 'reason' => 'Return', 'items' => [['allocation_id' => $sale->items()->first()->allocations()->first()->id, 'quantity' => 1]]])->assertForbidden();
    }

    public function test_partial_return_duplicate_and_excess_return_protection(): void
    {
        $this->receive();
        $sale = $this->checkout();
        $key = (string) Str::uuid();
        $first = $this->refund($sale, 1, ['request_key' => $key]);
        $again = $this->refund($sale, 1, ['request_key' => $key]);
        $this->assertSame($first->id, $again->id);
        try {
            $this->refund($sale, 2);
            $this->fail();
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('sales_returns', 1);
        $this->assertDatabaseCount('refunds', 1);
        $this->refund($sale, 1);
        $this->assertDatabaseCount('refunds', 2);
        $this->assertBalanced();
    }

    public function test_partial_receipt_and_over_receipt_and_duplicate_prevention(): void
    {
        $service = app(Purchasing::class);
        $order = $service->order($this->admin, $this->branch->id, ['supplier_id' => $this->supplier->id, 'items' => [['medicine_id' => $this->medicine->id, 'quantity' => 10, 'unit_cost' => '4.00']]]);
        $data = ['warehouse_id' => $this->warehouse, 'request_key' => (string) Str::uuid(), 'items' => [['item_id' => $order->items()->first()->id, 'quantity' => 4, 'lot_number' => 'TEST', 'expires_at' => '2027-01-01']]];
        $first = $service->receive($this->admin, $order, $data);
        $second = $service->receive($this->admin, $order, $data);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('partial', $order->fresh()->status);
        $data['request_key'] = (string) Str::uuid();
        $data['items'][0]['quantity'] = 7;
        try {
            $service->receive($this->admin, $order, $data);
            $this->fail();
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('goods_receipts', 1);
        $this->assertDatabaseCount('medicine_batches', 1);
        $this->assertBalanced();
    }

    public function test_cross_branch_record_and_warehouse_access_are_rejected(): void
    {
        $this->receive();
        $sale = $this->checkout();
        $other = Branch::create(['code' => 'OTHER', 'name' => 'Other']);
        $warehouse = $other->warehouses()->create(['name' => 'Other']);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Super Administrator')->first());
        $user->branches()->attach($other);
        $this->actingAs($user)->withSession(['branch_id' => $other->id])->get('/sales/'.$sale->id)->assertForbidden();
        $this->post('/branch', ['branch_id' => $this->branch->id])->assertForbidden();
        $data = $this->cart();
        $data['warehouse_id'] = $warehouse->id;
        $this->actingAs($this->admin)->withSession(['branch_id' => $this->branch->id])->post('/checkout', $data)->assertNotFound();
    }

    public function test_prescription_products_are_blocked(): void
    {
        $this->receive();
        $this->medicine->update(['prescription_required' => true]);
        $this->expectException(ValidationException::class);
        $this->checkout();
    }

    public function test_alert_refresh_is_deduplicated_and_resolves_when_condition_clears(): void
    {
        $batch = $this->receive(20, '2026-11-01');
        (new RefreshInventoryAlerts)->handle();
        (new RefreshInventoryAlerts)->handle();
        $this->assertDatabaseCount('operational_alerts', 1);
        $this->assertDatabaseHas('operational_alerts', ['key' => 'batch:'.$batch->id, 'resolved' => false]);
        $batch->update(['expires_at' => '2028-01-01']);
        (new RefreshInventoryAlerts)->handle();
        $this->assertDatabaseHas('operational_alerts', ['key' => 'batch:'.$batch->id, 'resolved' => true]);
    }

    public function test_http_validation_unique_barcode_and_product_archive(): void
    {
        $this->actingAs($this->admin)->withSession(['branch_id' => $this->branch->id]);
        $data = ['name' => 'Catalog product', 'sku' => 'MED2', 'barcode' => '12345', 'category' => 'Medicine', 'unit' => 'unit', 'prescription_required' => false, 'active' => true, 'selling_price' => '20.00', 'tax_basis_points' => 0, 'reorder_level' => 10];
        $this->post('/medicines', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('medicines', ['sku' => 'MED2']);
        $data['sku'] = 'MED3';
        $this->post('/medicines', $data)->assertSessionHasErrors('barcode');
        $m = Medicine::where('sku', 'MED2')->first();
        $data['sku'] = 'MED2';
        $data['active'] = false;
        $this->put('/medicines/'.$m->id, $data)->assertSessionHasNoErrors();
        $this->assertFalse($m->fresh()->active);
    }

    public function test_http_checkout_rejects_duplicate_medicine_and_invalid_money(): void
    {
        $this->receive();
        $data = $this->cart();
        $data['items'][] = $data['items'][0];
        $this->actingAs($this->admin)->withSession(['branch_id' => $this->branch->id])->post('/checkout', $data)->assertSessionHasErrors('items.0.medicine_id');
        $data = $this->cart();
        $data['payments'][0]['amount'] = '1.999';
        $this->post('/checkout', $data)->assertSessionHasErrors('payments.0.amount');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_login_logout_throttle_and_inactive_account(): void
    {
        $this->admin->update(['password' => Hash::make('StrongLocal123!')]);
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'StrongLocal123!'])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->admin);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->admin->active = false;
        $this->admin->save();
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'StrongLocal123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'no@example.test', 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => 'no@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
    }

    public function test_sensitive_routes_reject_guests(): void
    {
        foreach (['/', '/inventory', '/accounting', '/administration', '/sales', '/pos'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_posting_rejects_unbalanced_journals(): void
    {
        $this->expectException(\LogicException::class);
        DB::transaction(fn () => app(Ledger::class)->post($this->admin, $this->branch->id, 'test:unbalanced', 'Bad journal', [['1000', 100, 0], ['4000', 0, 99]]));
    }

    public function test_duplicate_source_journal_is_rejected(): void
    {
        DB::transaction(fn () => app(Ledger::class)->post($this->admin, $this->branch->id, 'unique:1', 'Journal', [['1000', 100, 0], ['4000', 0, 100]]));
        $this->expectException(QueryException::class);
        DB::transaction(fn () => app(Ledger::class)->post($this->admin, $this->branch->id, 'unique:1', 'Duplicate', [['1000', 100, 0], ['4000', 0, 100]]));
    }

    public function test_movement_history_cannot_be_edited(): void
    {
        $batch = $this->receive();
        $this->expectException(\LogicException::class);
        $batch->movements()->first()->update(['quantity_delta' => 999]);
    }

    public function test_expense_and_supplier_payment_retries_are_idempotent(): void
    {
        $this->receive();
        $data = ['supplier_id' => $this->supplier->id, 'amount' => '10.00', 'method' => 'cash', 'request_key' => (string) Str::uuid()];
        $service = app(Purchasing::class);
        $this->assertSame($service->pay($this->admin, $this->branch->id, $data), $service->pay($this->admin, $this->branch->id, $data));
        $this->assertDatabaseCount('supplier_payments', 1);
        $data = ['category' => 'Rent', 'description' => 'Monthly rent', 'amount' => '5.00', 'method' => 'bank', 'request_key' => (string) Str::uuid()];
        $service = app(Expenses::class);
        $this->assertSame($service->pay($this->admin, $this->branch->id, $data), $service->pay($this->admin, $this->branch->id, $data));
        $this->assertDatabaseCount('expenses', 1);
        $this->assertBalanced();
    }

    public function test_supplier_overpayment_rejected(): void
    {
        $this->receive();
        $this->expectException(ValidationException::class);
        app(Purchasing::class)->pay($this->admin, $this->branch->id, ['supplier_id' => $this->supplier->id, 'amount' => '40.01', 'method' => 'cash', 'request_key' => (string) Str::uuid()]);
    }

    public function test_money_rounding_does_not_use_float(): void
    {
        $this->assertSame(1001, Money::minor('10.01'));
        $this->assertSame('10.01', Money::decimal(1001));
        $this->assertSame(1, Money::tax(10, 500));
        $this->expectException(\InvalidArgumentException::class);
        Money::minor('1.999');
    }

    public function test_expiry_today_is_ineligible_and_next_day_is_eligible(): void
    {
        $today = $this->receive(4, now(config('pharmacy.timezone'))->toDateString());
        $tomorrow = $this->receive(4, now(config('pharmacy.timezone'))->addDay()->toDateString());
        $this->checkout();
        $this->assertSame(4, $today->fresh()->quantity);
        $this->assertSame(2, $tomorrow->fresh()->quantity);
    }

    public function test_decimal_storage_limit_has_a_validation_error_instead_of_truncation(): void
    {
        $this->expectException(ValidationException::class);
        Money::decimal(100000000000000);
    }
}

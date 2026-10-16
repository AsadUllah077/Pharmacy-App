<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\PurchaseAccount;
use App\Services\Purchasing;
use App\Services\SaleAccount;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartialPaymentsAndPurchaseReturnsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $branch;

    private int $warehouse;

    private int $supplier;

    private Medicine $medicine;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        $this->admin = User::where('email', 'admin@example.test')->first();
        $this->branch = Branch::where('code', 'MAIN')->value('id');
        $this->warehouse = Branch::find($this->branch)->warehouses()->value('id');
        $this->supplier = DB::table('suppliers')->value('id');
        $this->medicine = Medicine::first();
        $this->medicine->update(['selling_price' => '10.00', 'tax_basis_points' => 500]);
        $this->customer = Customer::create(['name' => 'Credit customer']);
        $this->actingAs($this->admin)->withSession(['branch_id' => $this->branch]);
    }

    private function received(int $quantity = 10): PurchaseOrder
    {
        $service = app(Purchasing::class);
        $order = $service->order($this->admin, $this->branch, ['supplier_id' => $this->supplier, 'items' => [['medicine_id' => $this->medicine->id, 'quantity' => $quantity, 'unit_cost' => '4.00']]]);
        $service->receive($this->admin, $order, ['warehouse_id' => $this->warehouse, 'request_key' => (string) Str::uuid(), 'items' => [['item_id' => $order->items()->value('id'), 'quantity' => $quantity, 'lot_number' => 'PAY-'.Str::random(6), 'expires_at' => now()->addYear()->toDateString()]]]);

        return $order;
    }

    private function sale(int $quantity = 4, string $paid = '12.00'): Sale
    {
        $this->post('/checkout', ['warehouse_id' => $this->warehouse, 'customer_id' => $this->customer->id, 'allow_partial' => true, 'request_key' => (string) Str::uuid(), 'items' => [['medicine_id' => $this->medicine->id, 'quantity' => $quantity, 'unit_discount' => '0.00']], 'payments' => $paid === '0.00' ? [] : [['method' => 'cash', 'amount' => $paid]]])->assertRedirect()->assertSessionHasNoErrors();

        return Sale::latest('id')->first();
    }

    private function pay(PurchaseOrder $order, string $amount): array
    {
        return ['supplier_id' => $this->supplier, 'purchase_order_id' => $order->id, 'request_key' => (string) Str::uuid(), 'amount' => $amount, 'method' => 'bank'];
    }

    private function purchaseReturn(PurchaseOrder $order, int $quantity, string $bucket = 'available', string $settlement = 'credit'): array
    {
        return ['request_key' => (string) Str::uuid(), 'reason' => 'Supplier accepted damaged goods', 'settlement' => $settlement, 'method' => 'bank', 'items' => [['batch_id' => $order->receipts()->first()->batches()->value('id'), 'quantity' => $quantity, 'stock_bucket' => $bucket]]];
    }

    private function balanced(): void
    {
        foreach (DB::table('journal_entries')->get() as $entry) {
            $lines = DB::table('journal_lines')->where('journal_entry_id', $entry->id);
            $this->assertSame(Money::minor((string) (clone $lines)->sum('debit')), Money::minor((string) (clone $lines)->sum('credit')));
        }
        $this->get('/accounting/stock-reconciliation')->assertInertia(fn (Assert $p) => $p->has('discrepancies', 0));
        $receivables = DB::table('journal_lines')->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')->where('code', '1100');
        $netReceivables = Money::minor((string) (clone $receivables)->sum('debit')) - Money::minor((string) (clone $receivables)->sum('credit'));
        $this->assertSame(Sale::all()->sum(fn ($sale) => Money::minor(SaleAccount::summary($sale)['due'])), $netReceivables);
        $payables = DB::table('journal_lines')->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')->where('code', '2000');
        $netPayables = Money::minor((string) (clone $payables)->sum('credit')) - Money::minor((string) (clone $payables)->sum('debit'));
        $this->assertSame(PurchaseOrder::all()->sum(fn ($order) => Money::minor(PurchaseAccount::summary($order->id)['due'])) - PurchaseAccount::availableCredit($this->branch, $this->supplier), $netPayables);
    }

    public function test_supplier_partial_payments_are_allocated_and_cannot_overpay_or_duplicate(): void
    {
        $order = $this->received();
        $payment = $this->pay($order, '15.00');
        $this->post('/supplier-payments', $payment)->assertSessionHasNoErrors();
        $this->post('/supplier-payments', $payment)->assertSessionHasNoErrors();
        $this->assertSame('25.00', PurchaseAccount::summary($order->id)['due']);
        $payment['amount'] = '16.00';
        $this->post('/supplier-payments', $payment)->assertStatus(409);
        $this->post('/supplier-payments', $this->pay($order, '25.01'))->assertSessionHasErrors('amount');
        $this->post('/supplier-payments', $this->pay($order, '25.00'))->assertSessionHasNoErrors();
        $this->assertSame('0.00', PurchaseAccount::summary($order->id)['due']);
        $this->assertDatabaseCount('supplier_payments', 2);
        $this->get('/supplier-payments?supplier_id='.$this->supplier)->assertOk()->assertInertia(fn (Assert $p) => $p->component('SupplierPayments')->has('history.data', 2)->where('orders.data.0.payment_summary.status', 'Paid'));
        $this->balanced();
    }

    public function test_supplier_level_payment_allocates_oldest_orders_and_respects_branch_and_supplier(): void
    {
        $first = $this->received();
        $second = $this->received();
        $data = $this->pay($second, '50.00');
        unset($data['purchase_order_id']);
        $this->post('/supplier-payments', $data)->assertSessionHasNoErrors();
        $this->assertSame('0.00', PurchaseAccount::summary($first->id)['due']);
        $this->assertSame('30.00', PurchaseAccount::summary($second->id)['due']);
        $other = Branch::where('code', 'NORTH')->value('id');
        $this->withSession(['branch_id' => $other])->post('/supplier-payments', $this->pay($second, '1.00'))->assertSessionHasErrors('purchase_order_id');
        $this->withSession(['branch_id' => $other])->get('/supplier-payments')->assertInertia(fn (Assert $p) => $p->has('history.data', 0)->has('orders.data', 0));
        $this->withSession(['branch_id' => $this->branch]);
        $this->balanced();
    }

    public function test_credit_sale_collections_and_returns_reduce_receivable_before_refunding(): void
    {
        $this->received();
        $sale = $this->sale(); // 42.00 total, 12 paid, 30 due.
        $this->assertSame('30.00', SaleAccount::summary($sale)['due']);
        $data = ['request_key' => (string) Str::uuid(), 'amount' => '10.00', 'method' => 'bank'];
        $this->post('/sales/'.$sale->id.'/payments', $data)->assertSessionHasNoErrors();
        $this->post('/sales/'.$sale->id.'/payments', $data)->assertSessionHasNoErrors();
        $this->assertSame('20.00', SaleAccount::summary($sale)['due']);
        $data['amount'] = '11.00';
        $this->post('/sales/'.$sale->id.'/payments', $data)->assertStatus(409);
        $this->post('/sales/'.$sale->id.'/payments', ['request_key' => (string) Str::uuid(), 'amount' => '20.01', 'method' => 'cash'])->assertSessionHasErrors('amount');
        $allocation = $sale->items()->first()->allocations()->value('id');
        $this->post('/sales/'.$sale->id.'/returns', ['request_key' => (string) Str::uuid(), 'reason' => 'First return', 'items' => [['allocation_id' => $allocation, 'quantity' => 1]]])->assertSessionHasNoErrors();
        $this->assertSame('9.50', SaleAccount::summary($sale)['due']);
        $this->assertDatabaseCount('refunds', 0);
        $this->post('/sales/'.$sale->id.'/returns', ['request_key' => (string) Str::uuid(), 'reason' => 'Remaining return', 'items' => [['allocation_id' => $allocation, 'quantity' => 3]]])->assertSessionHasNoErrors();
        $summary = SaleAccount::summary($sale);
        $this->assertSame('0.00', $summary['due']);
        $this->assertSame('22.00', $summary['refunded']); // Only money actually paid is refunded.
        $this->get('/sales/'.$sale->id)->assertInertia(fn (Assert $p) => $p->where('sale.payment_summary.status', 'Returned'));
        $this->balanced();
    }

    public function test_unpaid_sales_require_explicit_choice_and_named_customer_and_can_be_settled(): void
    {
        $order = $this->received();
        $sale = $this->sale(2, '0.00');
        $this->assertSame('21.00', SaleAccount::summary($sale)['due']);
        $data = ['warehouse_id' => $this->warehouse, 'allow_partial' => true, 'request_key' => (string) Str::uuid(), 'items' => [['medicine_id' => $this->medicine->id, 'quantity' => 1]], 'payments' => []];
        $this->post('/checkout', $data)->assertSessionHasErrors('payments');
        $this->assertDatabaseCount('sales', 1);
        $this->post('/sales/'.$sale->id.'/payments', ['request_key' => (string) Str::uuid(), 'amount' => '21.00', 'method' => 'cash'])->assertSessionHasNoErrors();
        $this->assertSame('Paid', SaleAccount::summary($sale)['status']);
        $this->balanced();
    }

    public function test_purchase_return_reduces_unpaid_balance_and_rejects_excess_wrong_batch_and_duplicates(): void
    {
        $order = $this->received();
        $this->post('/supplier-payments', $this->pay($order, '10.00'))->assertSessionHasNoErrors();
        $data = $this->purchaseReturn($order, 3);
        $this->post('/purchases/'.$order->id.'/returns', $data)->assertSessionHasNoErrors();
        $this->post('/purchases/'.$order->id.'/returns', $data)->assertSessionHasNoErrors();
        $this->assertSame('18.00', PurchaseAccount::summary($order->id)['due']);
        $this->assertSame(7, $order->receipts()->first()->batches()->first()->quantity);
        $this->assertDatabaseCount('purchase_returns', 1);
        $data['items'][0]['quantity'] = 2;
        $this->post('/purchases/'.$order->id.'/returns', $data)->assertStatus(409);
        $this->post('/purchases/'.$order->id.'/returns', $this->purchaseReturn($order, 8))->assertSessionHasErrors('items');
        $other = $this->received();
        $this->post('/purchases/'.$order->id.'/returns', $this->purchaseReturn($other, 1))->assertNotFound();
        $this->balanced();
    }

    public function test_paid_purchase_returns_create_credit_that_settles_future_receipts(): void
    {
        $order = $this->received();
        $this->post('/supplier-payments', $this->pay($order, '40.00'))->assertSessionHasNoErrors();
        $this->post('/purchases/'.$order->id.'/returns', $this->purchaseReturn($order, 2))->assertSessionHasNoErrors();
        $this->assertSame(800, PurchaseAccount::availableCredit($this->branch, $this->supplier));
        $next = $this->received();
        $this->assertSame('8.00', PurchaseAccount::summary($next->id)['credit_applied']);
        $this->assertSame('32.00', PurchaseAccount::summary($next->id)['due']);
        $this->assertSame(0, PurchaseAccount::availableCredit($this->branch, $this->supplier));
        $this->post('/supplier-payments', $this->pay($next, '32.00'))->assertSessionHasNoErrors();
        $this->assertSame('0.00', PurchaseAccount::summary($next->id)['due']);
        $this->balanced();
    }

    public function test_purchase_return_from_quarantine_can_record_actual_supplier_refund(): void
    {
        $order = $this->received();
        $batch = $order->receipts()->first()->batches()->first();
        $this->post('/supplier-payments', $this->pay($order, '40.00'))->assertSessionHasNoErrors();
        $this->post('/inventory/'.$batch->id.'/quarantine', ['reason' => 'Packaging damage'])->assertSessionHasNoErrors();
        $this->post('/purchases/'.$order->id.'/returns', $this->purchaseReturn($order, 2, 'quarantine', 'refund'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('purchase_returns', ['refund_amount' => '8.00', 'credit_amount' => '0.00', 'refund_method' => 'bank']);
        $this->assertSame(8, $batch->fresh()->quarantined_quantity);
        $this->assertSame('0.00', PurchaseAccount::summary($order->id)['due']);
        $this->balanced();
    }

    public function test_finance_and_return_actions_enforce_permissions_and_branch_isolation(): void
    {
        $order = $this->received();
        $sale = $this->sale();
        $north = Branch::where('code', 'NORTH')->value('id');
        $this->withSession(['branch_id' => $north])->post('/purchases/'.$order->id.'/returns', $this->purchaseReturn($order, 1))->assertNotFound();
        $this->post('/sales/'.$sale->id.'/payments', ['request_key' => (string) Str::uuid(), 'amount' => '1.00', 'method' => 'cash'])->assertNotFound();
        $this->withSession(['branch_id' => $this->branch])->actingAs(User::where('email', 'cashier@example.test')->first());
        $this->get('/supplier-payments')->assertForbidden();
        $this->post('/supplier-payments', $this->pay($order, '1.00'))->assertForbidden();
        $this->post('/purchases/'.$order->id.'/returns', $this->purchaseReturn($order, 1))->assertForbidden();
        $this->assertDatabaseCount('purchase_returns', 0);
    }

    public function test_credit_on_a_partially_received_order_applies_once_to_a_later_receipt(): void
    {
        $service = app(Purchasing::class);
        $order = $service->order($this->admin, $this->branch, ['supplier_id' => $this->supplier, 'items' => [['medicine_id' => $this->medicine->id, 'quantity' => 20, 'unit_cost' => '4.00']]]);
        $receipt = ['warehouse_id' => $this->warehouse, 'request_key' => (string) Str::uuid(), 'items' => [['item_id' => $order->items()->value('id'), 'quantity' => 10, 'lot_number' => 'FIRST', 'expires_at' => now()->addYear()->toDateString()]]];
        $service->receive($this->admin, $order, $receipt);
        $this->post('/supplier-payments', $this->pay($order, '40.00'))->assertSessionHasNoErrors();
        $this->post('/purchases/'.$order->id.'/returns', $this->purchaseReturn($order, 2))->assertSessionHasNoErrors();
        $this->assertSame(800, PurchaseAccount::availableCredit($this->branch, $this->supplier));
        $receipt['request_key'] = (string) Str::uuid();
        $receipt['items'][0]['lot_number'] = 'SECOND';
        $service->receive($this->admin, $order, $receipt);
        $this->assertSame('32.00', PurchaseAccount::summary($order->id)['due']);
        $this->assertSame('8.00', PurchaseAccount::summary($order->id)['credit_applied']);
        $this->assertSame(0, PurchaseAccount::availableCredit($this->branch, $this->supplier));
        $this->balanced();
    }

    public function test_credit_applies_to_existing_purchases_and_can_be_returned_without_double_counting(): void
    {
        $first = $this->received();
        $second = $this->received();
        $this->post('/supplier-payments', $this->pay($first, '40.00'))->assertSessionHasNoErrors();
        $this->post('/purchases/'.$first->id.'/returns', $this->purchaseReturn($first, 10))->assertSessionHasNoErrors();
        $this->assertSame('0.00', PurchaseAccount::summary($second->id)['due']);
        $this->post('/purchases/'.$second->id.'/returns', $this->purchaseReturn($second, 10))->assertSessionHasNoErrors();
        $this->assertSame(4000, PurchaseAccount::availableCredit($this->branch, $this->supplier));
        $this->balanced();
    }

    public function test_inventory_user_can_return_goods_but_cannot_record_a_supplier_cash_refund(): void
    {
        $order = $this->received();
        $this->post('/supplier-payments', $this->pay($order, '40.00'))->assertSessionHasNoErrors();
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Inventory Manager')->value('id'));
        $user->branches()->attach($this->branch);
        $this->actingAs($user)->post('/purchases/'.$order->id.'/returns', $this->purchaseReturn($order, 1, 'available', 'refund'))->assertForbidden();
        $this->assertDatabaseCount('purchase_returns', 0);
        $this->post('/purchases/'.$order->id.'/returns', $this->purchaseReturn($order, 1))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('purchase_returns', ['credit_amount' => '4.00', 'refund_amount' => '0.00']);
    }
}

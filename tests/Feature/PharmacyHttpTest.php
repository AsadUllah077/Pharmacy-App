<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PharmacyHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $branch;

    private int $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        $this->admin = User::where('email', 'admin@example.test')->first();
        $this->branch = Branch::where('code', 'MAIN')->value('id');
        $this->warehouse = Branch::find($this->branch)->warehouses()->value('id');
        $this->actingAs($this->admin)->withSession(['branch_id' => $this->branch]);
    }

    public function test_complete_receiving_checkout_and_refund_through_http(): void
    {
        $this->post('/suppliers', ['name' => 'HTTP supplier', 'active' => true])->assertRedirect()->assertSessionHasNoErrors();
        $supplier = DB::table('suppliers')->where('name', 'HTTP supplier')->value('id');
        $this->post('/customers', ['name' => 'Fictional customer', 'active' => true])->assertRedirect()->assertSessionHasNoErrors();
        $medicine = Medicine::first();
        $this->post('/purchases', ['supplier_id' => $supplier, 'items' => [['medicine_id' => $medicine->id, 'quantity' => 10, 'unit_cost' => '5.00']]])->assertRedirect('/purchases/1');
        $item = DB::table('purchase_order_items')->first();
        $payload = ['warehouse_id' => $this->warehouse, 'request_key' => (string) Str::uuid(), 'items' => [['item_id' => $item->id, 'quantity' => 10, 'lot_number' => 'HTTP-001', 'expires_at' => now()->addYear()->toDateString()]]];
        $this->post('/purchases/1/receive', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/purchases/1/receive', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('goods_receipts', 1);
        $this->getJson('/pos/search?warehouse_id='.$this->warehouse.'&q='.$medicine->sku)->assertOk()->assertJsonPath('0.available_stock', 10);
        $cart = ['warehouse_id' => $this->warehouse, 'request_key' => (string) Str::uuid(), 'items' => [['medicine_id' => $medicine->id, 'quantity' => 2, 'unit_discount' => '0.00', 'selling_price' => '0.01']], 'payments' => [['method' => 'cash', 'amount' => '100.00']]];
        $this->post('/checkout', $cart)->assertRedirect('/sales/1')->assertSessionHasNoErrors();
        $this->post('/checkout', $cart)->assertRedirect('/sales/1')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sales', 1);
        $sale = Sale::first();
        $this->assertSame('24.00', $sale->total);
        $this->assertSame('76.00', $sale->change);
        $this->assertSame(8, MedicineBatch::first()->quantity);
        $this->get('/sales/1')->assertOk()->assertInertia(fn (Assert $page) => $page->component('SaleDetail')->has('sale.items', 1)->has('sale.items.0.allocations', 1));
        $allocation = DB::table('sale_allocations')->first();
        $this->post('/sales/1/returns', ['request_key' => (string) Str::uuid(), 'reason' => 'Condition uncertain', 'items' => [['allocation_id' => $allocation->id, 'quantity' => 1]]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('refunds', ['amount' => '12.00']);
        $this->assertSame(1, MedicineBatch::first()->quarantined_quantity);
        $this->get('/accounting/stock-reconciliation')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Accounting/StockReconciliation')->has('discrepancies', 0));
    }

    public function test_all_implemented_screens_render_with_real_props(): void
    {
        foreach (['/', '/medicines', '/inventory', '/suppliers', '/customers', '/purchases', '/pos', '/sales', '/accounting', '/expenses', '/movements', '/notifications', '/audit', '/administration', '/profile'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_accounting_reports_filter_postings_by_branch_and_dates(): void
    {
        $otherBranch = Branch::where('code', 'NORTH')->value('id');
        foreach ([[$this->branch, '2026-10-01', '10.00'], [$this->branch, '2026-09-30', '20.00'], [$otherBranch, '2026-10-01', '30.00']] as [$branch, $date, $amount]) {
            $entry = DB::table('journal_entries')->insertGetId(['branch_id' => $branch, 'user_id' => $this->admin->id, 'source' => (string) Str::uuid(), 'description' => 'Report filter fixture', 'posting_date' => $date]);
            foreach ([['1000', $amount, '0.00'], ['4000', '0.00', $amount]] as [$code, $debit, $credit]) {
                DB::table('journal_lines')->insert(['journal_entry_id' => $entry, 'account_id' => DB::table('accounts')->where('code', $code)->value('id'), 'debit' => $debit, 'credit' => $credit]);
            }
        }
        foreach (['trial-balance' => 'TrialBalance', 'operating-result' => 'OperatingResult'] as $report => $component) {
            $this->get('/accounting/'.$report.'?from=2026-10-01&to=2026-10-01')->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Accounting/'.$component)->has('balances', 2)
                ->where('balances.0.debit', fn ($amount) => (float) $amount === 10.0)
                ->where('balances.1.credit', fn ($amount) => (float) $amount === 10.0)
                ->where('from', '2026-10-01')->where('to', '2026-10-01'));
        }
        $this->get('/accounting/journal-entries?from=2026-10-01&to=2026-10-01')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Accounting/JournalEntries')->has('journals.data', 1)->where('journals.data.0.branch_id', $this->branch));
        $this->get('/accounting')->assertInertia(fn (Assert $page) => $page->component('Accounting')->missing('balances')->missing('journals')->missing('discrepancies'));
    }

    public function test_accounting_report_permissions_validation_and_pagination(): void
    {
        for ($i = 0; $i < 21; $i++) {
            DB::table('journal_entries')->insert(['branch_id' => $this->branch, 'user_id' => $this->admin->id, 'source' => 'pagination:'.$i, 'description' => 'Pagination fixture', 'posting_date' => '2026-10-01']);
        }
        $this->get('/accounting/journal-entries?from=2026-10-01&page=2')->assertInertia(fn (Assert $page) => $page
            ->has('journals.data', 1)->where('journals.total', 21)
            ->where('journals.prev_page_url', fn ($url) => str_contains($url, 'from=2026-10-01')));
        foreach (['trial-balance', 'operating-result', 'journal-entries'] as $report) {
            $this->get('/accounting/'.$report.'?from=2026-10-15&to=2026-10-01')->assertSessionHasErrors('to');
        }
        $this->get('/accounting/unknown-report')->assertNotFound();
        $cashier = User::where('email', 'cashier@example.test')->first();
        foreach (['trial-balance', 'operating-result', 'stock-reconciliation', 'journal-entries'] as $report) {
            $this->actingAs($cashier)->get('/accounting/'.$report)->assertForbidden();
            $this->app['auth']->forgetGuards();
            $this->get('/accounting/'.$report)->assertRedirect('/login');
        }
    }

    public function test_inventory_search_retains_expiry_filters_and_branch_isolation(): void
    {
        $medicine = Medicine::first();
        $other = Branch::where('code', 'NORTH')->first();
        foreach ([[$this->branch, $this->warehouse, '2020-01-01'], [$this->branch, $this->warehouse, '2099-01-01'], [$other->id, $other->warehouses()->value('id'), '2020-01-01']] as [$branch,$warehouse,$expiry]) {
            MedicineBatch::create(['medicine_id' => $medicine->id, 'branch_id' => $branch, 'warehouse_id' => $warehouse, 'lot_number' => 'LOT-'.$expiry, 'expires_at' => $expiry, 'received_quantity' => 5, 'quantity' => 5, 'unit_cost' => '2.00']);
        }
        $this->get('/inventory?q='.urlencode($medicine->name).'&filter=expired')->assertInertia(fn (Assert $page) => $page->has('batches.data', 1)->where('batches.data.0.branch_id', $this->branch));
    }

    public function test_dashboard_uses_business_date_for_expired_stock(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(0, 0));
        $medicine = Medicine::first();
        MedicineBatch::create(['medicine_id' => $medicine->id, 'branch_id' => $this->branch, 'warehouse_id' => $this->warehouse, 'lot_number' => 'TODAY', 'expires_at' => now(config('pharmacy.timezone'))->toDateString(), 'received_quantity' => 7, 'quantity' => 7, 'unit_cost' => '2.00']);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('stats.expired_units', 7));
    }

    public function test_quarantine_creates_movement_and_blocks_sale_search_stock(): void
    {
        $medicine = Medicine::first();
        $batch = MedicineBatch::create(['medicine_id' => $medicine->id, 'branch_id' => $this->branch, 'warehouse_id' => $this->warehouse, 'lot_number' => 'Q', 'quantity' => 3, 'received_quantity' => 3, 'unit_cost' => '2.00']);
        $this->post('/inventory/'.$batch->id.'/quarantine', ['reason' => 'Suspected storage issue'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, $batch->fresh()->quantity);
        $this->assertSame(3, $batch->fresh()->quarantined_quantity);
        $this->assertDatabaseHas('stock_movements', ['type' => 'quarantine', 'quantity_delta' => -3, 'quarantine_delta' => 3]);
        $this->getJson('/pos/search?warehouse_id='.$this->warehouse.'&q='.$medicine->sku)->assertJsonPath('0.available_stock', null);
    }

    public function test_admin_can_assign_roles_branches_and_disable_user(): void
    {
        $role = Role::where('name', 'Cashier')->value('id');
        $data = ['name' => 'New cashier', 'email' => 'new@example.test', 'password' => 'StrongPassword123!', 'active' => true, 'role_ids' => [$role], 'branch_ids' => [$this->branch]];
        $this->post('/administration/users', $data)->assertRedirect()->assertSessionHasNoErrors();
        $user = User::where('email', $data['email'])->first();
        $this->assertTrue($user->hasPermission('sales.create'));
        $this->assertFalse($user->hasPermission('accounting.manage'));
        $data['id'] = $user->id;
        $data['active'] = false;
        $data['password'] = '';
        $this->post('/administration/users', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->active);
        $this->actingAs($user->fresh())->get('/')->assertForbidden();
    }

    public function test_branch_creation_and_switch_assigns_only_authorized_access(): void
    {
        $this->post('/administration/branches', ['name' => 'South pharmacy', 'code' => 'SOUTH', 'timezone' => 'Asia/Karachi', 'warehouse' => 'South store'])->assertRedirect()->assertSessionHasNoErrors();
        $branch = Branch::where('code', 'SOUTH')->first();
        $this->assertTrue($this->admin->hasBranch($branch->id));
        $this->assertCount(1, $branch->warehouses);
        $this->post('/branch', ['branch_id' => $branch->id])->assertRedirect('/')->assertSessionHas('branch_id', $branch->id);
        $cashier = User::where('email', 'cashier@example.test')->first();
        $this->actingAs($cashier)->post('/branch', ['branch_id' => $branch->id])->assertForbidden();
    }

    public function test_password_reset_and_change_revoke_sessions(): void
    {
        Notification::fake();
        auth()->logout();
        $this->post('/forgot-password', ['email' => $this->admin->email])->assertRedirect()->assertSessionHasNoErrors();
        Notification::assertSentTo($this->admin, ResetPassword::class);
        $token = Password::createToken($this->admin);
        $this->post('/reset-password', ['email' => $this->admin->email, 'token' => $token, 'password' => 'AnotherStrong123!', 'password_confirmation' => 'AnotherStrong123!'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('AnotherStrong123!', $this->admin->fresh()->password));
        $this->actingAs($this->admin->fresh())->withSession(['branch_id' => $this->branch])->put('/profile/password', ['current_password' => 'AnotherStrong123!', 'password' => 'FinalStrong123!', 'password_confirmation' => 'FinalStrong123!'])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_cannot_change_own_access_and_cashier_cannot_administer(): void
    {
        $this->post('/administration/users', ['id' => $this->admin->id, 'name' => $this->admin->name, 'email' => $this->admin->email, 'active' => false, 'role_ids' => [Role::first()->id], 'branch_ids' => [$this->branch]])->assertUnprocessable();
        $this->actingAs(User::where('email', 'cashier@example.test')->first())->get('/administration')->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Services\Ledger;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BalanceSheetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        $this->admin = User::where('email', 'admin@example.test')->first();
        $this->branch = Branch::where('code', 'MAIN')->value('id');
        $this->actingAs($this->admin)->withSession(['branch_id' => $this->branch]);
        DB::table('accounts')->insert(['code' => '3000', 'name' => 'Owner capital', 'type' => 'equity']);
    }

    private function postJournal(array $lines, string $date, ?int $branch = null): void
    {
        $id = DB::transaction(fn () => app(Ledger::class)->post($this->admin, $branch ?? $this->branch, (string) Str::uuid(), 'Balance sheet fixture', $lines));
        DB::table('journal_entries')->where('id', $id)->update(['posting_date' => $date]);
    }

    public function test_balance_sheet_carries_prior_balances_and_includes_profit_without_future_or_other_branch_entries(): void
    {
        $this->postJournal([['1000', 100000, 0], ['3000', 0, 100000]], '2025-12-31');
        $this->postJournal([['1200', 30000, 0], ['2000', 0, 30000]], '2026-10-01');
        $this->postJournal([['1100', 20000, 0], ['4000', 0, 20000], ['5000', 8000, 0], ['1200', 0, 8000]], '2026-10-15');
        $this->postJournal([['6000', 2000, 0], ['1000', 0, 2000]], '2026-10-15');
        $this->postJournal([['1000', 50000, 0], ['4000', 0, 50000]], '2026-10-16');
        $this->postJournal([['1000', 90000, 0], ['4000', 0, 90000]], '2026-10-15', Branch::where('code', 'NORTH')->value('id'));
        $this->get('/accounting/balance-sheet?as_of=2026-10-15&from=2026-10-01')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Accounting/BalanceSheet')->where('asOf', '2026-10-15')
            ->where('sheet.totals.assets', '1400.00')->where('sheet.totals.liabilities', '300.00')
            ->where('sheet.totals.equity', '1100.00')->where('sheet.totals.liabilities_and_equity', '1400.00')
            ->where('sheet.totals.difference', '0.00')->where('sheet.balanced', true)
            ->has('sheet.assets', 3)->has('sheet.equity', 2)->where('sheet.equity.1.amount', '100.00'));
        $this->get('/accounting/balance-sheet?as_of=2026-10-16')->assertInertia(fn (Assert $p) => $p->where('sheet.totals.assets', '1900.00')->where('sheet.totals.equity', '1600.00'));
    }

    public function test_balance_sheet_preserves_negative_balances_and_accumulated_losses(): void
    {
        $this->postJournal([['6000', 2500, 0], ['1000', 0, 2500]], '2026-10-01');
        $this->postJournal([['2000', 1000, 0], ['1200', 0, 1000]], '2026-10-01');
        $this->get('/accounting/balance-sheet?as_of=2026-10-01')->assertInertia(fn (Assert $p) => $p
            ->where('sheet.totals.assets', '-35.00')->where('sheet.totals.liabilities', '-10.00')
            ->where('sheet.totals.equity', '-25.00')->where('sheet.equity.0.amount', '-25.00')
            ->where('sheet.balanced', true));
    }

    public function test_empty_report_is_zero_and_uses_business_date_and_validates_dates(): void
    {
        $this->travelTo(Carbon::parse('2026-10-16 20:00:00', 'UTC'));
        $this->get('/accounting/balance-sheet')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('asOf', '2026-10-17')->where('sheet.totals.assets', '0.00')
            ->where('sheet.totals.equity', '0.00')->where('sheet.balanced', true)->has('sheet.assets', 0));
        $this->get('/accounting/balance-sheet?as_of=2026-02-30')->assertSessionHasErrors('as_of');
        $this->get('/accounting/balance-sheet?as_of=wrong')->assertSessionHasErrors('as_of');
    }

    public function test_report_flags_unbalanced_postings_and_enforces_accounting_access(): void
    {
        $id = DB::table('journal_entries')->insertGetId(['branch_id' => $this->branch, 'user_id' => $this->admin->id, 'source' => 'malformed-fixture', 'description' => 'Deliberately unbalanced test fixture', 'posting_date' => '2026-10-01']);
        DB::table('journal_lines')->insert(['journal_entry_id' => $id, 'account_id' => DB::table('accounts')->where('code', '1000')->value('id'), 'debit' => '0.01', 'credit' => '0.00']);
        $this->get('/accounting/balance-sheet?as_of=2026-10-01')->assertInertia(fn (Assert $p) => $p->where('sheet.balanced', false)->where('sheet.totals.difference', '0.01'));
        $this->actingAs(User::where('email', 'cashier@example.test')->first())->get('/accounting/balance-sheet')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->get('/accounting/balance-sheet')->assertRedirect('/login');
    }
}

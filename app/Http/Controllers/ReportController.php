<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Models\MedicineBatch;
use App\Models\Sale;
use App\Services\Expenses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function dashboard(Request $request)
    {
        $branch = $request->session()->get('branch_id');
        $today = now(config('pharmacy.timezone'))->startOfDay()->utc();
        $sales = Sale::where('branch_id', $branch);
        $stats = ['today_sales' => (clone $sales)->where('created_at', '>=', $today)->sum('total'), 'month_sales' => (clone $sales)->where('created_at', '>=', now(config('pharmacy.timezone'))->startOfMonth()->utc())->sum('total'), 'transactions' => (clone $sales)->count(), 'inventory_value' => MedicineBatch::where('branch_id', $branch)->selectRaw('COALESCE(SUM((quantity + quarantined_quantity) * unit_cost),0) as total')->value('total'), 'expired_units' => MedicineBatch::where('branch_id', $branch)->where('expires_at', '<=', now(config('pharmacy.timezone'))->toDateString())->sum('quantity')];
        $days = collect(range(6, 0))->map(function ($offset) use ($branch) {
            $start = now(config('pharmacy.timezone'))->startOfDay()->subDays($offset);

            return ['label' => $start->format('D'), 'total' => Sale::where('branch_id', $branch)->whereBetween('created_at', [$start->copy()->utc(), $start->copy()->endOfDay()->utc()])->sum('total')];
        });

        return Inertia::render('Dashboard', ['stats' => $stats, 'days' => $days, 'recent' => Sale::where('branch_id', $branch)->latest()->limit(6)->get(['id', 'invoice_number', 'total', 'created_at']), 'alerts' => DB::table('operational_alerts')->where('branch_id', $branch)->where('resolved', false)->latest()->limit(5)->get()]);
    }

    public function accounting()
    {
        return Inertia::render('Accounting');
    }

    public function accountingReport(Request $request, string $report)
    {
        $branch = $request->session()->get('branch_id');
        if ($report === 'stock-reconciliation') {
            $discrepancies = DB::table('medicine_batches as b')->leftJoin('stock_movements as m', 'm.medicine_batch_id', '=', 'b.id')->where('b.branch_id', $branch)->groupBy('b.id', 'b.lot_number', 'b.quantity', 'b.quarantined_quantity')->select('b.id', 'b.lot_number', 'b.quantity', 'b.quarantined_quantity')->selectRaw('COALESCE(SUM(m.quantity_delta),0) as ledger_quantity, COALESCE(SUM(m.quarantine_delta),0) as ledger_quarantine')->havingRaw('b.quantity <> COALESCE(SUM(m.quantity_delta),0) OR b.quarantined_quantity <> COALESCE(SUM(m.quarantine_delta),0)')->limit(100)->get();

            return Inertia::render('Accounting/StockReconciliation', ['discrepancies' => $discrepancies]);
        }

        $dates = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $filters = ['from' => $dates['from'] ?? '', 'to' => $dates['to'] ?? ''];
        if ($report === 'journal-entries') {
            $journals = DB::table('journal_entries')->where('branch_id', $branch)
                ->when($filters['from'], fn ($q, $v) => $q->where('posting_date', '>=', $v))
                ->when($filters['to'], fn ($q, $v) => $q->where('posting_date', '<=', $v))
                ->orderByDesc('posting_date')->orderByDesc('id')->paginate(20)->withQueryString();

            return Inertia::render('Accounting/JournalEntries', [...$filters, 'journals' => $journals]);
        }

        $balances = DB::table('journal_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')->where('journal_entries.branch_id', $branch)->when($dates['from'] ?? null, fn ($q, $v) => $q->where('posting_date', '>=', $v))->when($dates['to'] ?? null, fn ($q, $v) => $q->where('posting_date', '<=', $v))->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')->select('accounts.code', 'accounts.name', 'accounts.type')->selectRaw('SUM(debit) as debit, SUM(credit) as credit')->orderBy('accounts.code')->get();

        return Inertia::render($report === 'trial-balance' ? 'Accounting/TrialBalance' : 'Accounting/OperatingResult', [...$filters, 'balances' => $balances]);
    }

    public function expenses(Request $request)
    {
        return Inertia::render('Expenses', ['records' => DB::table('expenses')->where('branch_id', $request->session()->get('branch_id'))->latest()->paginate(20)]);
    }

    public function payExpense(PaymentRequest $request, Expenses $service)
    {
        $service->pay($request->user(), $request->session()->get('branch_id'), $request->validated());

        return back()->with('success', 'Expense and payment posted.');
    }

    public function audit(Request $request)
    {
        return Inertia::render('Records', ['title' => 'Audit trail', 'columns' => ['id', 'user_id', 'action', 'subject', 'created_at'], 'records' => DB::table('audit_logs')->where('branch_id', $request->session()->get('branch_id'))->latest()->paginate(30)]);
    }

    public function alerts(Request $request)
    {
        return Inertia::render('Records', ['title' => 'Operational alerts', 'columns' => ['id', 'message', 'resolved', 'updated_at'], 'records' => DB::table('operational_alerts')->where('branch_id', $request->session()->get('branch_id'))->orderBy('resolved')->latest()->paginate(30)]);
    }

    public function export(Request $request)
    {
        $branch = $request->session()->get('branch_id');

        return response()->streamDownload(function () use ($branch) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Invoice', 'UTC date', 'Subtotal', 'Discount', 'Tax', 'Total']);
            foreach (Sale::where('branch_id', $branch)->orderBy('id')->cursor() as $sale) {
                fputcsv($out, [$sale->invoice_number, $sale->created_at, $sale->subtotal, $sale->discount, $sale->tax, $sale->total]);
            } fclose($out);
        }, 'sales.csv', ['Content-Type' => 'text/csv']);
    }
}

<?php

namespace App\Services;

use App\Support\Money;
use Illuminate\Support\Facades\DB;

final class BalanceSheet
{
    public function forBranch(int $branch, string $asOf): array
    {
        $balances = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.branch_id', $branch)
            ->where('journal_entries.posting_date', '<=', $asOf)
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->select('accounts.code', 'accounts.name', 'accounts.type')
            ->selectRaw('SUM(debit) as debit, SUM(credit) as credit')
            ->orderBy('accounts.code')->get();

        $groups = ['assets' => [], 'liabilities' => [], 'equity' => []];
        $totals = ['assets' => 0, 'liabilities' => 0, 'equity' => 0];
        $earnings = 0;
        foreach ($balances as $balance) {
            $debit = Money::minor((string) $balance->debit);
            $credit = Money::minor((string) $balance->credit);
            if (in_array($balance->type, ['income', 'contra_income', 'expense'], true)) {
                $earnings += $credit - $debit;

                continue;
            }
            $group = match ($balance->type) {
                'asset' => 'assets',
                'liability' => 'liabilities',
                'equity' => 'equity',
                default => null,
            };
            if ($group === null) {
                continue;
            }
            $amount = $group === 'assets' ? $debit - $credit : $credit - $debit;
            $totals[$group] += $amount;
            if ($amount !== 0) {
                $groups[$group][] = ['code' => $balance->code, 'name' => $balance->name, 'amount' => Money::decimal($amount)];
            }
        }
        // All unclosed income and expense balances, not just the selected calendar year.
        $groups['equity'][] = ['code' => '', 'name' => 'Accumulated earnings / (loss)', 'amount' => Money::decimal($earnings)];
        $totals['equity'] += $earnings;
        $liabilitiesAndEquity = $totals['liabilities'] + $totals['equity'];
        $difference = $totals['assets'] - $liabilitiesAndEquity;

        return [
            ...$groups,
            'totals' => array_map(fn ($amount) => Money::decimal($amount), [...$totals, 'liabilities_and_equity' => $liabilitiesAndEquity, 'difference' => $difference]),
            'balanced' => $difference === 0,
        ];
    }
}

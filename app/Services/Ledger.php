<?php

namespace App\Services;

use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use LogicException;

final class Ledger
{
    /** Lines are [account code, debit minor units, credit minor units]. Called inside source transaction. */
    public function post(User $user, int $branch, string $source, string $description, array $lines): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Posting requires a source transaction.');
        }
        $debits = array_sum(array_column($lines, 1));
        $credits = array_sum(array_column($lines, 2));
        if ($debits !== $credits || $debits <= 0) {
            throw new LogicException('Unbalanced or empty journal.');
        }
        $id = DB::table('journal_entries')->insertGetId(['branch_id' => $branch, 'user_id' => $user->id, 'source' => $source, 'description' => $description, 'posting_date' => now(config('pharmacy.timezone'))->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        foreach ($lines as [$code,$debit,$credit]) {
            if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0)) {
                throw new LogicException('Invalid journal line.');
            }
            if ($debit === 0 && $credit === 0) {
                continue;
            }
            $account = DB::table('accounts')->where('code', $code)->value('id');
            if (! $account) {
                throw new LogicException('Missing account mapping: '.$code);
            }
            DB::table('journal_lines')->insert(['journal_entry_id' => $id, 'account_id' => $account, 'debit' => Money::decimal($debit), 'credit' => Money::decimal($credit), 'created_at' => now(), 'updated_at' => now()]);
        }

        return $id;
    }

    public static function paymentAccount(string $method): string
    {
        return $method === 'cash' ? '1000' : '1010';
    }
}

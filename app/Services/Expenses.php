<?php

namespace App\Services;

use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

final class Expenses
{
    public function __construct(private Ledger $ledger) {}

    public function pay(User $user, int $branch, array $data): int
    {
        BranchAccess::check($user, $branch, 'accounting.manage');

        return DB::transaction(function () use ($user, $branch, $data) {
            BranchAccess::lock($branch);
            $existing = DB::table('expenses')->where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->branch_id === $branch && $existing->user_id === $user->id, 409);

                return $existing->id;
            }
            $amount = Money::minor((string) $data['amount']);
            $id = DB::table('expenses')->insertGetId(['branch_id' => $branch, 'user_id' => $user->id, 'category' => $data['category'], 'description' => $data['description'], 'amount' => Money::decimal($amount), 'method' => $data['method'], 'request_key' => $data['request_key'], 'created_at' => now(), 'updated_at' => now()]);
            $this->ledger->post($user, $branch, 'expense:'.$id, $data['description'], [['6000', $amount, 0], [Ledger::paymentAccount($data['method']), 0, $amount]]);
            Audit::record($user, $branch, 'expense.paid', 'expense:'.$id);

            return $id;
        }, 3);
    }
}

<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaleAccount
{
    public function __construct(private Ledger $ledger) {}

    public static function summary(Sale $sale): array
    {
        $paid = Money::minor((string) $sale->payments()->sum('amount'));
        $returned = Money::minor((string) $sale->returns()->sum('total'));
        $refunded = Money::minor((string) DB::table('refunds')->whereIn('sales_return_id', $sale->returns()->select('id'))->sum('amount'));
        $net = Money::minor($sale->total) - $returned;
        $due = max(0, $net - $paid + $refunded);

        return ['paid' => Money::decimal($paid), 'returned' => Money::decimal($returned), 'refunded' => Money::decimal($refunded), 'net_total' => Money::decimal($net), 'due' => Money::decimal($due), 'status' => $net === 0 ? 'Returned' : ($due === 0 ? 'Paid' : ($paid > 0 ? 'Partially paid' : 'Unpaid'))];
    }

    public function collect(User $user, Sale $sale, array $data): int
    {
        BranchAccess::check($user, $sale->branch_id, 'sales.create');

        return DB::transaction(function () use ($user, $sale, $data) {
            BranchAccess::lock($sale->branch_id);
            $hash = hash('sha256', json_encode($data));
            $existing = DB::table('payments')->where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->sale_id === $sale->id && $existing->user_id === $user->id && hash_equals($existing->request_hash, $hash), 409);

                return $existing->id;
            }
            $amount = Money::minor((string) $data['amount']);
            $due = Money::minor(self::summary($sale)['due']);
            if ($amount <= 0 || $amount > $due) {
                throw ValidationException::withMessages(['amount' => 'Payment must be positive and cannot exceed the invoice balance.']);
            }
            $id = DB::table('payments')->insertGetId(['sale_id' => $sale->id, 'user_id' => $user->id, 'method' => $data['method'], 'amount' => Money::decimal($amount), 'request_key' => $data['request_key'], 'request_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            $this->ledger->post($user, $sale->branch_id, 'sale-payment:'.$id, 'Payment for '.$sale->invoice_number, [[Ledger::paymentAccount($data['method']), $amount, 0], ['1100', 0, $amount]]);
            Audit::record($user, $sale->branch_id, 'sale.payment_received', 'sale-payment:'.$id);

            return $id;
        }, 3);
    }
}

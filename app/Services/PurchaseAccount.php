<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

final class PurchaseAccount
{
    private static function sum(string $table, int $order, string $column): int
    {
        return Money::minor((string) DB::table($table)->where('purchase_order_id', $order)->sum($column));
    }

    public static function summary(int $order): array
    {
        $received = self::sum('goods_receipts', $order, 'total');
        $returned = self::sum('purchase_returns', $order, 'total');
        $paid = self::sum('supplier_payment_allocations', $order, 'amount');
        $credits = self::sum('purchase_credit_allocations', $order, 'amount');
        $issued = self::sum('purchase_returns', $order, 'credit_amount');
        $refunded = self::sum('purchase_returns', $order, 'refund_amount');
        $due = max(0, $received - $returned - $paid - $credits + $issued + $refunded);

        return ['received' => Money::decimal($received), 'returned' => Money::decimal($returned), 'paid' => Money::decimal($paid), 'credit_applied' => Money::decimal($credits), 'supplier_credit_issued' => Money::decimal($issued), 'supplier_refunds' => Money::decimal($refunded), 'due' => Money::decimal($due), 'status' => $received === 0 ? 'Not received' : ($received === $returned ? 'Returned' : ($due === 0 ? 'Paid' : ($paid + $credits > 0 ? 'Partially paid' : 'Unpaid')))];
    }

    public static function availableCredit(int $branch, int $supplier): int
    {
        $returns = DB::table('purchase_returns')->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_returns.purchase_order_id')->where('purchase_orders.branch_id', $branch)->where('purchase_orders.supplier_id', $supplier);
        $issued = Money::minor((string) (clone $returns)->sum('credit_amount'));
        $allocated = Money::minor((string) DB::table('purchase_credit_allocations')->whereIn('purchase_return_id', (clone $returns)->select('purchase_returns.id'))->sum('amount'));

        return $issued - $allocated;
    }

    /** Called under the branch transaction lock. Apply supplier credits to oldest outstanding orders. */
    public static function applyCredits(int $branch, int $supplier): void
    {
        $credits = DB::table('purchase_returns')->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_returns.purchase_order_id')->where('purchase_orders.branch_id', $branch)->where('purchase_orders.supplier_id', $supplier)->where('credit_amount', '>', 0)->orderBy('purchase_returns.id')->select('purchase_returns.*')->get();
        foreach ($credits as $credit) {
            $remaining = Money::minor((string) $credit->credit_amount) - Money::minor((string) DB::table('purchase_credit_allocations')->where('purchase_return_id', $credit->id)->sum('amount'));
            foreach (PurchaseOrder::where('branch_id', $branch)->where('supplier_id', $supplier)->orderBy('id')->get() as $order) {
                if ($remaining <= 0) {
                    break;
                }
                $amount = min($remaining, Money::minor(self::summary($order->id)['due']));
                if ($amount > 0) {
                    DB::table('purchase_credit_allocations')->insert(['purchase_return_id' => $credit->id, 'purchase_order_id' => $order->id, 'amount' => Money::decimal($amount), 'created_at' => now(), 'updated_at' => now()]);
                    $remaining -= $amount;
                }
            }
        }
    }
}

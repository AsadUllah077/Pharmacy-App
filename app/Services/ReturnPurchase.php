<?php

namespace App\Services;

use App\Models\MedicineBatch;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReturnPurchase
{
    public function __construct(private Ledger $ledger) {}

    public function execute(User $user, PurchaseOrder $order, array $data): int
    {
        BranchAccess::check($user, $order->branch_id, 'purchases.return');

        return DB::transaction(function () use ($user, $order, $data) {
            BranchAccess::lock($order->branch_id);
            $hash = hash('sha256', json_encode($data));
            $existing = DB::table('purchase_returns')->where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->purchase_order_id === $order->id && $existing->user_id === $user->id && hash_equals($existing->request_hash, $hash), 409);

                return $existing->id;
            }
            PurchaseAccount::applyCredits($order->branch_id, $order->supplier_id);
            $due = Money::minor(PurchaseAccount::summary($order->id)['due']);
            $id = DB::table('purchase_returns')->insertGetId(['purchase_order_id' => $order->id, 'user_id' => $user->id, 'request_key' => $data['request_key'], 'request_hash' => $hash, 'reason' => $data['reason'], 'total' => '0.00', 'created_at' => now(), 'updated_at' => now()]);
            $total = 0;
            foreach ($data['items'] as $input) {
                $batch = MedicineBatch::where('branch_id', $order->branch_id)->whereHas('receipt', fn ($q) => $q->where('purchase_order_id', $order->id))->whereKey($input['batch_id'])->lockForUpdate()->firstOrFail();
                $column = $input['stock_bucket'] === 'quarantine' ? 'quarantined_quantity' : 'quantity';
                $quantity = (int) $input['quantity'];
                // A unit cannot be returned to the supplier more than once, including resold customer returns.
                $returned = (int) DB::table('purchase_return_items')->where('medicine_batch_id', $batch->id)->sum('quantity');
                if ($quantity < 1 || $quantity > $batch->$column || $returned + $quantity > $batch->received_quantity) {
                    throw ValidationException::withMessages(['items' => 'Return exceeds the selected batch stock or the original received quantity.']);
                }
                $batch->decrement($column, $quantity);
                $amount = Money::minor($batch->unit_cost) * $quantity;
                $total += $amount;
                DB::table('purchase_return_items')->insert(['purchase_return_id' => $id, 'medicine_batch_id' => $batch->id, 'stock_bucket' => $input['stock_bucket'], 'quantity' => $quantity, 'amount' => Money::decimal($amount)]);
                StockMovement::create(['medicine_batch_id' => $batch->id, 'user_id' => $user->id, 'type' => 'purchase_return', 'quantity_delta' => $column === 'quantity' ? -$quantity : 0, 'quarantine_delta' => $column === 'quarantined_quantity' ? -$quantity : 0, 'reference' => 'purchase-return:'.$id, 'reason' => $data['reason']]);
            }
            $excess = max(0, $total - $due);
            $refund = $data['settlement'] === 'refund' ? $excess : 0;
            if ($refund > 0 && ! $user->hasPermission('accounting.manage')) {
                abort(403, 'Accounting permission is required to record a supplier refund.');
            }
            DB::table('purchase_returns')->where('id', $id)->update(['total' => Money::decimal($total), 'credit_amount' => Money::decimal($excess - $refund), 'refund_amount' => Money::decimal($refund), 'refund_method' => $refund > 0 ? $data['method'] : null]);
            $this->ledger->post($user, $order->branch_id, 'purchase-return:'.$id, 'Purchase return for PO-'.$order->id, [['2000', $total - $refund, 0], [Ledger::paymentAccount($data['method'] ?? 'bank'), $refund, 0], ['1200', 0, $total]]);
            PurchaseAccount::applyCredits($order->branch_id, $order->supplier_id);
            Audit::record($user, $order->branch_id, 'purchase.returned', 'purchase-return:'.$id);

            return $id;
        }, 3);
    }
}

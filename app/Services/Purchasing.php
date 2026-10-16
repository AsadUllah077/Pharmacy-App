<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class Purchasing
{
    public function __construct(private Ledger $ledger) {}

    public function order(User $user, int $branch, array $data): PurchaseOrder
    {
        BranchAccess::check($user, $branch, 'purchases.create');

        return DB::transaction(function () use ($user, $branch, $data) {
            abort_unless(Supplier::where('active', true)->whereKey($data['supplier_id'])->exists(), 422, 'Supplier is inactive.');
            $order = PurchaseOrder::create(['branch_id' => $branch, 'supplier_id' => $data['supplier_id'], 'user_id' => $user->id]);
            foreach ($data['items'] as $item) {
                abort_unless(Medicine::whereKey($item['medicine_id'])->where('active', true)->exists(), 422, 'Medicine is inactive.');
                $order->items()->create($item);
            }
            Audit::record($user, $branch, 'purchase.ordered', 'purchase:'.$order->id);

            return $order;
        });
    }

    public function receive(User $user, PurchaseOrder $order, array $data): GoodsReceipt
    {
        BranchAccess::check($user, $order->branch_id, 'inventory.receive');
        BranchAccess::warehouse($order->branch_id, (int) $data['warehouse_id']);

        return DB::transaction(function () use ($user, $order, $data) {
            BranchAccess::lock($order->branch_id);
            $existing = GoodsReceipt::where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->purchase_order_id === $order->id && $existing->user_id === $user->id, 409);

                return $existing;
            }
            $order = PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $receipt = GoodsReceipt::create(['purchase_order_id' => $order->id, 'warehouse_id' => $data['warehouse_id'], 'user_id' => $user->id, 'request_key' => $data['request_key'], 'total' => '0.00']);
            $total = 0;
            foreach ($data['items'] as $input) {
                $item = $order->items()->whereKey($input['item_id'])->lockForUpdate()->firstOrFail();
                if ($input['quantity'] > $item->quantity - $item->received_quantity) {
                    throw ValidationException::withMessages(['items' => 'Receipt exceeds the outstanding order quantity.']);
                }
                $batch = MedicineBatch::create(['medicine_id' => $item->medicine_id, 'branch_id' => $order->branch_id, 'warehouse_id' => $data['warehouse_id'], 'goods_receipt_id' => $receipt->id, 'lot_number' => $input['lot_number'], 'expires_at' => $input['expires_at'] ?? null, 'received_quantity' => $input['quantity'], 'quantity' => $input['quantity'], 'unit_cost' => $item->unit_cost]);
                StockMovement::create(['medicine_batch_id' => $batch->id, 'user_id' => $user->id, 'type' => 'purchase_receipt', 'quantity_delta' => $input['quantity'], 'reference' => 'receipt:'.$receipt->id]);
                $item->increment('received_quantity', $input['quantity']);
                $total += Money::minor($item->unit_cost) * $input['quantity'];
            }
            $receipt->update(['total' => Money::decimal($total)]);
            $order->update(['status' => $order->items()->whereColumn('received_quantity', '<', 'quantity')->exists() ? 'partial' : 'received']);
            $this->ledger->post($user, $order->branch_id, 'receipt:'.$receipt->id, 'Goods received; supplier liability', [['1200', $total, 0], ['2000', 0, $total]]);
            PurchaseAccount::applyCredits($order->branch_id, $order->supplier_id);
            Audit::record($user, $order->branch_id, 'inventory.received', 'receipt:'.$receipt->id);

            return $receipt;
        }, 3);
    }

    public function pay(User $user, int $branch, array $data): int
    {
        BranchAccess::check($user, $branch, 'accounting.manage');

        return DB::transaction(function () use ($user, $branch, $data) {
            BranchAccess::lock($branch);
            $hash = hash('sha256', json_encode($data));
            $existing = DB::table('supplier_payments')->where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->branch_id === $branch && $existing->user_id === $user->id && $existing->supplier_id === (int) $data['supplier_id'] && ($existing->request_hash ? hash_equals($existing->request_hash, $hash) : (Money::minor((string) $existing->amount) === Money::minor((string) $data['amount']) && $existing->method === $data['method'])), 409);

                return $existing->id;
            }
            PurchaseAccount::applyCredits($branch, (int) $data['supplier_id']);
            $orders = PurchaseOrder::where('branch_id', $branch)->where('supplier_id', $data['supplier_id'])
                ->when($data['purchase_order_id'] ?? null, fn ($q, $id) => $q->whereKey($id))->orderBy('id')->get();
            if (! empty($data['purchase_order_id']) && $orders->isEmpty()) {
                throw ValidationException::withMessages(['purchase_order_id' => 'Select an order from this supplier and branch.']);
            }
            $amount = Money::minor((string) $data['amount']);
            $outstanding = $orders->sum(fn ($order) => Money::minor(PurchaseAccount::summary($order->id)['due']));
            if ($amount <= 0 || $amount > $outstanding) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds the remaining purchase balance.']);
            }
            $id = DB::table('supplier_payments')->insertGetId(['branch_id' => $branch, 'supplier_id' => $data['supplier_id'], 'user_id' => $user->id, 'amount' => Money::decimal($amount), 'method' => $data['method'], 'request_hash' => $hash, 'request_key' => $data['request_key'], 'created_at' => now(), 'updated_at' => now()]);
            $remaining = $amount;
            foreach ($orders as $order) {
                $allocated = min($remaining, Money::minor(PurchaseAccount::summary($order->id)['due']));
                if ($allocated > 0) {
                    DB::table('supplier_payment_allocations')->insert(['supplier_payment_id' => $id, 'purchase_order_id' => $order->id, 'amount' => Money::decimal($allocated)]);
                    $remaining -= $allocated;
                }
                if ($remaining === 0) {
                    break;
                }
            }
            $this->ledger->post($user, $branch, 'supplier-payment:'.$id, 'Supplier payment', [['2000', $amount, 0], [Ledger::paymentAccount($data['method']), 0, $amount]]);
            Audit::record($user, $branch, 'supplier.paid', 'supplier-payment:'.$id);

            return $id;
        }, 3);
    }
}

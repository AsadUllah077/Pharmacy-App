<?php

namespace App\Services;

use App\Models\MedicineBatch;
use App\Models\Sale;
use App\Models\SaleAllocation;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReturnSale
{
    public function __construct(private Ledger $ledger) {}

    public function execute(User $user, Sale $sale, array $data): SalesReturn
    {
        BranchAccess::check($user, $sale->branch_id, 'sales.refund');

        return DB::transaction(function () use ($user, $sale, $data) {
            BranchAccess::lock($sale->branch_id);
            $existing = SalesReturn::where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->sale_id === $sale->id && $existing->user_id === $user->id, 409);

                return $existing;
            }
            $return = SalesReturn::create(['sale_id' => $sale->id, 'user_id' => $user->id, 'request_key' => $data['request_key'], 'reason' => $data['reason'], 'total' => '0.00']);
            $net = $tax = $cost = 0;
            foreach ($data['items'] as $input) {
                $allocation = SaleAllocation::whereHas('item', fn ($q) => $q->where('sale_id', $sale->id))->whereKey($input['allocation_id'])->lockForUpdate()->firstOrFail();
                $q = (int) $input['quantity'];
                if ($q > $allocation->quantity - $allocation->returned_quantity) {
                    throw ValidationException::withMessages(['items' => 'Return exceeds the remaining returnable quantity.']);
                }
                $item = $allocation->item;
                $lineNet = (Money::minor((string) $item->unit_price) - Money::minor((string) $item->unit_discount)) * $q;
                $lineTax = Money::minor((string) $item->unit_tax) * $q;
                $lineCost = Money::minor((string) $allocation->unit_cost) * $q;
                $allocation->increment('returned_quantity', $q);
                MedicineBatch::whereKey($allocation->medicine_batch_id)->increment('quarantined_quantity', $q);
                StockMovement::create(['medicine_batch_id' => $allocation->medicine_batch_id, 'user_id' => $user->id, 'type' => 'customer_return', 'quantity_delta' => 0, 'quarantine_delta' => $q, 'reference' => 'return:'.$return->id, 'reason' => $data['reason']]);
                DB::table('sales_return_items')->insert(['sales_return_id' => $return->id, 'sale_allocation_id' => $allocation->id, 'quantity' => $q, 'amount' => Money::decimal($lineNet + $lineTax), 'created_at' => now(), 'updated_at' => now()]);
                $net += $lineNet;
                $tax += $lineTax;
                $cost += $lineCost;
            }
            $return->update(['total' => Money::decimal($net + $tax)]);
            DB::table('refunds')->insert(['sales_return_id' => $return->id, 'amount' => Money::decimal($net + $tax), 'method' => 'cash', 'created_at' => now(), 'updated_at' => now()]);
            $this->ledger->post($user, $sale->branch_id, 'return:'.$return->id, 'Cash refund; stock quarantined', [['4100', $net, 0], ['2100', $tax, 0], ['1000', 0, $net + $tax], ['1200', $cost, 0], ['5000', 0, $cost]]);
            Audit::record($user, $sale->branch_id, 'sale.returned', 'return:'.$return->id);

            return $return;
        }, 3);
    }
}

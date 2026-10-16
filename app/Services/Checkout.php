<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class Checkout
{
    public function __construct(private Ledger $ledger) {}

    public function execute(User $user, int $branch, array $data): Sale
    {
        BranchAccess::check($user, $branch, 'sales.create');
        BranchAccess::warehouse($branch, (int) $data['warehouse_id']);
        $hash = hash('sha256', json_encode($data));

        return DB::transaction(function () use ($user, $branch, $data, $hash) {
            $organization = BranchAccess::lock($branch);
            $existing = Sale::where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->branch_id === $branch && $existing->user_id === $user->id && hash_equals($existing->request_hash, $hash), 409, 'This checkout key was already used for another request.');

                return $existing;
            }
            if (! empty($data['customer_id'])) {
                abort_unless(Customer::whereKey($data['customer_id'])->where('active', true)->exists(), 422);
            }
            $organization->increment('invoice_sequence');
            $sale = Sale::create(['branch_id' => $branch, 'warehouse_id' => $data['warehouse_id'], 'user_id' => $user->id, 'customer_id' => $data['customer_id'] ?? null, 'invoice_number' => $organization->code.'-'.str_pad((string) $organization->invoice_sequence, 8, '0', STR_PAD_LEFT), 'request_key' => $data['request_key'], 'request_hash' => $hash, 'subtotal' => '0.00', 'discount' => '0.00', 'tax' => '0.00', 'total' => '0.00', 'cost' => '0.00']);
            $subtotal = $discount = $tax = $cost = 0;
            $items = collect($data['items'])->sortBy('medicine_id');
            foreach ($items as $input) {
                $medicine = Medicine::where('active', true)->whereKey($input['medicine_id'])->firstOrFail();
                if ($medicine->prescription_required) {
                    throw ValidationException::withMessages(['items' => 'Restricted medicines require a prescription dispensing workflow, which is not yet available.']);
                }
                $price = Money::minor($medicine->selling_price);
                $unitDiscount = Money::minor((string) ($input['unit_discount'] ?? '0'));
                if ($unitDiscount > 0 && ! $user->hasPermission('sales.discount')) {
                    abort(403);
                }
                if ($unitDiscount * 10000 > $price * config('pharmacy.max_discount_basis_points')) {
                    throw ValidationException::withMessages(['items' => 'Discount exceeds the configured limit.']);
                }
                $unitTax = Money::tax($price - $unitDiscount, $medicine->tax_basis_points);
                $quantity = (int) $input['quantity'];
                $remaining = $quantity;
                $line = $sale->items()->create(['medicine_id' => $medicine->id, 'description' => $medicine->name, 'quantity' => $quantity, 'unit_price' => Money::decimal($price), 'unit_discount' => Money::decimal($unitDiscount), 'unit_tax' => Money::decimal($unitTax), 'total' => Money::decimal(($price - $unitDiscount + $unitTax) * $quantity)]);
                $batches = MedicineBatch::sellable()->where('branch_id', $branch)->where('warehouse_id', $data['warehouse_id'])->where('medicine_id', $medicine->id)->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')->orderBy('expires_at')->orderBy('id')->lockForUpdate()->get();
                foreach ($batches as $batch) {
                    $take = min($remaining, $batch->quantity);
                    if ($take <= 0) {
                        continue;
                    }
                    $updated = MedicineBatch::whereKey($batch->id)->where('quantity', '>=', $take)->decrement('quantity', $take);
                    if ($updated !== 1) {
                        throw ValidationException::withMessages(['items' => 'Stock changed. Retry checkout.']);
                    }
                    $line->allocations()->create(['medicine_batch_id' => $batch->id, 'quantity' => $take, 'unit_cost' => $batch->unit_cost]);
                    StockMovement::create(['medicine_batch_id' => $batch->id, 'user_id' => $user->id, 'type' => 'sale', 'quantity_delta' => -$take, 'reference' => 'sale:'.$sale->id]);
                    $cost += Money::minor($batch->unit_cost) * $take;
                    $remaining -= $take;
                    if ($remaining === 0) {
                        break;
                    }
                }
                if ($remaining > 0) {
                    throw ValidationException::withMessages(['items' => 'Insufficient eligible stock for '.$medicine->name.'.']);
                }
                $subtotal += $price * $quantity;
                $discount += $unitDiscount * $quantity;
                $tax += $unitTax * $quantity;
            }
            $total = $subtotal - $discount + $tax;
            $paid = 0;
            $cash = 0;
            foreach ($data['payments'] as $payment) {
                $amount = Money::minor((string) $payment['amount']);
                $paid += $amount;
                if ($payment['method'] === 'cash') {
                    $cash += $amount;
                }
            }
            $outstanding = max(0, $total - $paid);
            $change = max(0, $paid - $total);
            if ($outstanding > 0 && (empty($data['allow_partial']) || empty($data['customer_id']))) {
                throw ValidationException::withMessages(['payments' => 'For partial or unpaid sales, enable partial payment and select a named customer.']);
            }
            if ($change > $cash || $total <= 0) {
                throw ValidationException::withMessages(['payments' => 'Payment must cover the total. Only cash overpayment can be given as change.']);
            }
            $cashChange = $change;
            $paymentLines = [];
            foreach ($data['payments'] as $payment) {
                $amount = Money::minor((string) $payment['amount']);
                if ($payment['method'] === 'cash') {
                    $deduct = min($cashChange, $amount);
                    $amount -= $deduct;
                    $cashChange -= $deduct;
                }
                if ($amount === 0) {
                    continue;
                }
                $sale->payments()->create(['method' => $payment['method'], 'amount' => Money::decimal($amount)]);
                $paymentLines[] = [Ledger::paymentAccount($payment['method']), $amount, 0];
            }
            $sale->update(['subtotal' => Money::decimal($subtotal), 'discount' => Money::decimal($discount), 'tax' => Money::decimal($tax), 'total' => Money::decimal($total), 'cost' => Money::decimal($cost), 'change' => Money::decimal($change)]);
            $this->ledger->post($user, $branch, 'sale:'.$sale->id, $sale->invoice_number, array_merge($paymentLines, [['1100', $outstanding, 0], ['4000', 0, $subtotal - $discount], ['2100', 0, $tax], ['5000', $cost, 0], ['1200', 0, $cost]]));
            Audit::record($user, $branch, 'sale.completed', 'sale:'.$sale->id, ['invoice' => $sale->invoice_number]);

            return $sale;
        }, 3);
    }
}

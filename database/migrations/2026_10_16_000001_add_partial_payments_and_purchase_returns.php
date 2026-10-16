<?php

use App\Support\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $t) {
            $t->string('request_key', 64)->nullable()->unique();
            $t->string('request_hash', 64)->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('supplier_payments', fn (Blueprint $t) => $t->string('request_hash', 64)->nullable());
        Schema::table('sales_returns', function (Blueprint $t) {
            $t->decimal('balance_reduction', 14, 2)->default(0);
        });
        Schema::create('supplier_payment_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_payment_id')->constrained()->restrictOnDelete();
            $t->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $t->decimal('amount', 14, 2);
            $t->unique(['supplier_payment_id', 'purchase_order_id']);
        });
        Schema::create('purchase_returns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('request_key', 64)->unique();
            $t->string('request_hash', 64);
            $t->text('reason');
            $t->decimal('total', 14, 2);
            $t->decimal('credit_amount', 14, 2)->default(0);
            $t->decimal('refund_amount', 14, 2)->default(0);
            $t->string('refund_method')->nullable();
            $t->timestamps();
        });
        Schema::create('purchase_return_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_return_id')->constrained()->restrictOnDelete();
            $t->foreignId('medicine_batch_id')->constrained()->restrictOnDelete();
            $t->string('stock_bucket');
            $t->unsignedInteger('quantity');
            $t->decimal('amount', 14, 2);
        });
        Schema::create('purchase_credit_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_return_id')->constrained()->restrictOnDelete();
            $t->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $t->decimal('amount', 14, 2);
            $t->timestamps();
        });
        DB::table('accounts')->insertOrIgnore(['code' => '1100', 'name' => 'Customer receivables', 'type' => 'asset', 'created_at' => now(), 'updated_at' => now()]);

        foreach (DB::table('roles')->whereIn('name', ['Branch Manager', 'Inventory Manager', 'Purchasing Officer', 'Accountant'])->get() as $role) {
            $permissions = json_decode($role->permissions, true);
            if (! in_array('purchases.return', $permissions, true)) {
                $permissions[] = 'purchases.return';
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
            }
        }

        // Preserve existing supplier payments by allocating them to received orders, oldest first.
        foreach (DB::table('supplier_payments')->orderBy('id')->get() as $payment) {
            $remaining = Money::minor((string) $payment->amount);
            $orders = DB::table('purchase_orders')->where('branch_id', $payment->branch_id)->where('supplier_id', $payment->supplier_id)->orderBy('id')->get();
            foreach ($orders as $order) {
                $received = Money::minor((string) DB::table('goods_receipts')->where('purchase_order_id', $order->id)->sum('total'));
                $paid = Money::minor((string) DB::table('supplier_payment_allocations')->where('purchase_order_id', $order->id)->sum('amount'));
                $amount = min($remaining, max(0, $received - $paid));
                if ($amount > 0) {
                    DB::table('supplier_payment_allocations')->insert(['supplier_payment_id' => $payment->id, 'purchase_order_id' => $order->id, 'amount' => Money::decimal($amount)]);
                    $remaining -= $amount;
                }
                if ($remaining === 0) {
                    break;
                }
            }
            if ($remaining !== 0) {
                throw new RuntimeException('Existing supplier payment cannot be allocated. Review payment '.$payment->id.' before migrating.');
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_credit_allocations');
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('supplier_payment_allocations');
        Schema::table('supplier_payments', fn (Blueprint $t) => $t->dropColumn('request_hash'));
        Schema::table('sales_returns', fn (Blueprint $t) => $t->dropColumn('balance_reduction'));
        Schema::table('payments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('user_id');
            $t->dropColumn(['request_key', 'request_hash']);
        });
    }
};

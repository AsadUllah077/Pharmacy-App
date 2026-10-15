<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('active')->default(true);
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->json('permissions');
            $t->timestamps();
        });
        Schema::create('role_user', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('role_id')->constrained()->restrictOnDelete();
            $t->primary(['user_id', 'role_id']);
        });
        Schema::create('branches', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('timezone')->default('Asia/Karachi');
            $t->unsignedBigInteger('invoice_sequence')->default(0);
            $t->timestamps();
        });
        Schema::create('branch_user', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->primary(['user_id', 'branch_id']);
        });
        Schema::create('warehouses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->timestamps();
            $t->unique(['branch_id', 'name']);
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value');
        });
        Schema::create('medicines', function (Blueprint $t) {
            $t->id();
            $t->string('sku')->unique();
            $t->string('barcode')->nullable()->unique();
            $t->string('name')->index();
            $t->string('generic_name')->nullable()->index();
            $t->string('manufacturer')->nullable();
            $t->string('category')->default('Medicine');
            $t->string('strength')->nullable();
            $t->string('dosage_form')->nullable();
            $t->string('unit')->default('unit');
            $t->text('storage')->nullable();
            $t->boolean('prescription_required')->default(false);
            $t->boolean('active')->default(true);
            $t->decimal('selling_price', 14, 2);
            $t->unsignedInteger('tax_basis_points')->default(0);
            $t->unsignedInteger('reorder_level')->default(10);
            $t->timestamps();
        });
        foreach (['suppliers', 'customers'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('phone')->nullable();
                $t->string('email')->nullable();
                $t->text('address')->nullable();
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
        }
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('status')->default('ordered');
            $t->timestamps();
            $t->index(['branch_id', 'created_at']);
        });
        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $t->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('received_quantity')->default(0);
            $t->decimal('unit_cost', 14, 2);
            $t->timestamps();
            $t->unique(['purchase_order_id', 'medicine_id']);
        });
        Schema::create('goods_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $t->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('request_key', 64)->unique();
            $t->decimal('total', 14, 2);
            $t->timestamps();
        });
        Schema::create('medicine_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $t->foreignId('goods_receipt_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('lot_number');
            $t->date('expires_at')->nullable();
            $t->unsignedInteger('received_quantity');
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('quarantined_quantity')->default(0);
            $t->decimal('unit_cost', 14, 2);
            $t->string('status')->default('available');
            $t->timestamps();
            $t->index(['branch_id', 'warehouse_id', 'medicine_id', 'expires_at']);
        });
        Schema::create('supplier_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->decimal('amount', 14, 2);
            $t->string('method');
            $t->string('request_key', 64)->unique();
            $t->timestamps();
        });
        Schema::create('sales', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('invoice_number')->unique();
            $t->string('request_key', 64)->unique();
            $t->string('request_hash', 64);
            $t->decimal('subtotal', 14, 2);
            $t->decimal('discount', 14, 2);
            $t->decimal('tax', 14, 2);
            $t->decimal('total', 14, 2);
            $t->decimal('cost', 14, 2);
            $t->decimal('change', 14, 2)->default(0);
            $t->timestamps();
            $t->index(['branch_id', 'created_at']);
        });
        Schema::create('sale_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_id')->constrained()->restrictOnDelete();
            $t->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $t->string('description');
            $t->unsignedInteger('quantity');
            $t->decimal('unit_price', 14, 2);
            $t->decimal('unit_discount', 14, 2);
            $t->decimal('unit_tax', 14, 2);
            $t->decimal('total', 14, 2);
            $t->timestamps();
        });
        Schema::create('sale_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $t->foreignId('medicine_batch_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('returned_quantity')->default(0);
            $t->decimal('unit_cost', 14, 2);
            $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_id')->constrained()->restrictOnDelete();
            $t->string('method');
            $t->decimal('amount', 14, 2);
            $t->timestamps();
        });
        Schema::create('sales_returns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('request_key', 64)->unique();
            $t->text('reason');
            $t->decimal('total', 14, 2);
            $t->timestamps();
        });
        Schema::create('sales_return_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_return_id')->constrained()->restrictOnDelete();
            $t->foreignId('sale_allocation_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->decimal('amount', 14, 2);
            $t->timestamps();
        });
        Schema::create('refunds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_return_id')->unique()->constrained()->restrictOnDelete();
            $t->string('method')->default('cash');
            $t->decimal('amount', 14, 2);
            $t->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('medicine_batch_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('type');
            $t->integer('quantity_delta');
            $t->integer('quarantine_delta')->default(0);
            $t->string('reference');
            $t->text('reason')->nullable();
            $t->timestamps();
            $t->index(['medicine_batch_id', 'created_at']);
        });
        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('type');
            $t->timestamps();
        });
        Schema::create('journal_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('source')->unique();
            $t->string('description');
            $t->date('posting_date');
            $t->timestamps();
            $t->index(['branch_id', 'posting_date']);
        });
        Schema::create('journal_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $t->foreignId('account_id')->constrained()->restrictOnDelete();
            $t->decimal('debit', 14, 2)->default(0);
            $t->decimal('credit', 14, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('category');
            $t->string('description');
            $t->decimal('amount', 14, 2);
            $t->string('method');
            $t->string('request_key', 64)->unique();
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('action');
            $t->string('subject');
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['branch_id', 'created_at']);
        });
        Schema::create('operational_alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('key')->unique();
            $t->string('message');
            $t->boolean('resolved')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['operational_alerts', 'audit_logs', 'expenses', 'journal_lines', 'journal_entries', 'accounts', 'stock_movements', 'refunds', 'sales_return_items', 'sales_returns', 'payments', 'sale_allocations', 'sale_items', 'sales', 'supplier_payments', 'medicine_batches', 'goods_receipts', 'purchase_order_items', 'purchase_orders', 'customers', 'suppliers', 'medicines', 'settings', 'warehouses', 'branch_user', 'branches', 'role_user', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('active'));
    }
};

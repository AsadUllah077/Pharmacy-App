<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['Super Administrator' => ['*'], 'Pharmacy Owner' => ['*'], 'Branch Manager' => ['dashboard.view', 'medicines.view', 'medicines.create', 'medicines.update', 'inventory.view', 'inventory.receive', 'inventory.adjust', 'sales.view', 'sales.create', 'sales.discount', 'sales.refund', 'purchases.view', 'purchases.create', 'suppliers.view', 'suppliers.manage', 'customers.view', 'customers.manage', 'accounting.view', 'reports.view', 'audit_logs.view'], 'Pharmacist' => ['dashboard.view', 'medicines.view', 'inventory.view', 'sales.view', 'sales.create', 'customers.view'], 'Cashier' => ['dashboard.view', 'sales.view', 'sales.create', 'medicines.view', 'customers.view'], 'Inventory Manager' => ['dashboard.view', 'medicines.view', 'medicines.create', 'medicines.update', 'inventory.view', 'inventory.receive', 'inventory.adjust', 'purchases.view'], 'Purchasing Officer' => ['dashboard.view', 'suppliers.view', 'suppliers.manage', 'purchases.view', 'purchases.create', 'inventory.receive', 'medicines.view'], 'Accountant' => ['dashboard.view', 'accounting.view', 'accounting.manage', 'suppliers.view', 'purchases.view', 'reports.view'], 'Auditor' => ['dashboard.view', 'accounting.view', 'reports.view', 'audit_logs.view', 'inventory.view', 'sales.view', 'purchases.view'], 'Read-Only Manager' => ['dashboard.view', 'accounting.view', 'reports.view', 'inventory.view', 'sales.view', 'purchases.view', 'medicines.view']];
        foreach ($roles as $name => $permissions) {
            Role::firstOrCreate(['name' => $name], ['permissions' => $permissions]);
        }
        foreach ([['1000', 'Cash', 'asset'], ['1010', 'Bank / card / wallet clearing', 'asset'], ['1200', 'Inventory including quarantine', 'asset'], ['2000', 'Accounts payable', 'liability'], ['2100', 'Tax payable', 'liability'], ['4000', 'Sales revenue net of discounts', 'income'], ['4100', 'Sales returns', 'contra_income'], ['5000', 'Cost of goods sold', 'expense'], ['6000', 'Operating expenses', 'expense']] as [$code,$name,$type]) {
            DB::table('accounts')->updateOrInsert(['code' => $code], ['name' => $name, 'type' => $type, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Branch;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Medicine;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ReferenceSeeder::class);
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Central Pharmacy']);
        $branch->warehouses()->firstOrCreate(['name' => 'Main dispensary']);
        $second = Branch::firstOrCreate(['code' => 'NORTH'], ['name' => 'North Branch']);
        $second->warehouses()->firstOrCreate(['name' => 'North dispensary']);
        foreach ([['admin@example.test', 'Local Administrator', 'Super Administrator'], ['cashier@example.test', 'Local Cashier', 'Cashier']] as [$email,$name,$role]) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make('ChangeMe123!Local')]);
            $user->roles()->syncWithoutDetaching([Role::where('name', $role)->value('id')]);
            $user->branches()->syncWithoutDetaching($role === 'Cashier' ? [$branch->id] : [$branch->id, $second->id]);
        }
        Supplier::firstOrCreate(['name' => 'Fictional Medical Supply'], ['phone' => '000-0000000']);
        foreach ([['PARA500', 'Paracetamol 500 mg', 'Paracetamol', '12.00'], ['ORS001', 'Oral rehydration salts', 'Oral rehydration salts', '45.00'], ['BAND01', 'Sterile bandage', 'Medical supply', '80.00']] as [$sku,$name,$generic,$price]) {
            Medicine::firstOrCreate(['sku' => $sku], ['name' => $name, 'generic_name' => $generic, 'selling_price' => $price, 'category' => 'Medicine', 'unit' => 'unit']);
        }
        // No invented sales or stock balances. Receive stock through an actual purchase.
    }
}

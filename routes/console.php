<?php

use App\Jobs\RefreshInventoryAlerts;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('pharmacy:alerts', function () {
    RefreshInventoryAlerts::dispatchSync();
    $this->info('Inventory alerts refreshed.');
})->purpose('Refresh deduplicated low-stock and expiry alerts');
Schedule::job(new RefreshInventoryAlerts)->hourly()->withoutOverlapping();

Artisan::command('pharmacy:admin {email} {--name=Owner}', function () {
    $email = (string) $this->argument('email');
    $password = $this->secret('Password (12+ characters, mixed case and numbers)');
    $data = Validator::make(['email' => $email, 'password' => $password], ['email' => 'required|email|unique:users,email', 'password' => ['required', Password::min(12)->mixedCase()->numbers()]])->validate();
    DB::transaction(function () use ($data) {
        $role = Role::where('name', 'Super Administrator')->firstOrFail();
        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main Pharmacy']);
        $branch->warehouses()->firstOrCreate(['name' => 'Main dispensary']);
        $user = User::create(['name' => $this->option('name'), 'email' => $data['email'], 'password' => $data['password']]);
        $user->roles()->attach($role);
        $user->branches()->attach($branch);
        Audit::record($user, $branch->id, 'user.bootstrap', 'user:'.$user->id);
    });
    $this->info('Administrator created. No password was written to output.');
})->purpose('Provision the first administrator interactively after ReferenceSeeder');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

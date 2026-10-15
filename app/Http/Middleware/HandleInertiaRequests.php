<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return [...parent::share($request), 'auth' => fn () => $user ? ['user' => $user->only('id', 'name', 'email'), 'permissions' => $user->permissions(), 'branches' => $user->branches()->with('warehouses')->get(), 'branch_id' => (int) $request->session()->get('branch_id', 0)] : null, 'flash' => ['success' => fn () => $request->session()->get('success')], 'business' => ['name' => 'PharmaFlow', 'currency' => config('pharmacy.currency'), 'timezone' => config('pharmacy.timezone')]];
    }
}

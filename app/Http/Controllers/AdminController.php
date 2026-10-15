<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function index()
    {
        return Inertia::render('Administration', ['users' => User::with('roles', 'branches')->orderBy('name')->paginate(20), 'roles' => Role::orderBy('name')->get(), 'branches' => Branch::with('warehouses')->get()]);
    }

    public function saveUser(Request $request)
    {
        $data = $request->validate(['id' => 'nullable|integer|exists:users,id', 'name' => 'required|string|max:200', 'email' => ['required', 'email', 'max:200', Rule::unique('users')->ignore($request->input('id'))], 'password' => 'nullable|string|min:12|max:200', 'active' => 'required|boolean', 'role_ids' => 'required|array|min:1', 'role_ids.*' => 'integer|exists:roles,id', 'branch_ids' => 'required|array|min:1', 'branch_ids.*' => 'integer|exists:branches,id']);
        abort_if((int) ($data['id'] ?? 0) === $request->user()->id, 422, 'Ask another administrator to change your own access.');
        DB::transaction(function () use ($request, $data) {
            $user = isset($data['id']) ? User::findOrFail($data['id']) : new User;
            if (! $user->exists && empty($data['password'])) {
                throw ValidationException::withMessages(['password' => 'A password is required for new users.']);
            }
            $user->fill(collect($data)->only('name', 'email')->all());
            $user->active = $data['active'];
            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }
            $user->save();
            $user->roles()->sync($data['role_ids']);
            $user->branches()->sync($data['branch_ids']);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            Audit::record($request->user(), $request->session()->get('branch_id'), 'user.access_changed', 'user:'.$user->id);
        });

        return back()->with('success', 'User saved; existing sessions revoked.');
    }

    public function branch(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'code' => 'required|alpha_dash|max:20|unique:branches,code', 'timezone' => 'required|timezone', 'warehouse' => 'required|string|max:150']);
        DB::transaction(function () use ($request, $data) {
            $branch = Branch::create(collect($data)->only('name', 'code', 'timezone')->all());
            $branch->warehouses()->create(['name' => $data['warehouse']]);
            $request->user()->branches()->attach($branch);
            Audit::record($request->user(), $branch->id, 'branch.created', 'branch:'.$branch->id);
        });

        return back()->with('success', 'Branch and warehouse created.');
    }

    public function switch(Request $request)
    {
        $data = $request->validate(['branch_id' => 'required|integer']);
        abort_unless($request->user()->hasBranch($data['branch_id']), 403);
        $request->session()->put('branch_id', (int) $data['branch_id']);

        return redirect('/');
    }
}

<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use App\Models\Warehouse;

final class BranchAccess
{
    public static function check(User $user, int $branch, string $permission): void
    {
        abort_unless($user->hasPermission($permission) && $user->hasBranch($branch), 403);
    }

    public static function warehouse(int $branch, int $warehouse): Warehouse
    {
        return Warehouse::where('branch_id', $branch)->findOrFail($warehouse);
    }

    public static function lock(int $branch): Branch
    {
        return Branch::whereKey($branch)->lockForUpdate()->firstOrFail();
    }
}

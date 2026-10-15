<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function view(User $user, Sale $sale): bool
    {
        return $user->hasPermission('sales.view') && $user->hasBranch($sale->branch_id);
    }
}

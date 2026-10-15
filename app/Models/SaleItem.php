<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $guarded = ['id'];

    public function allocations()
    {
        return $this->hasMany(SaleAllocation::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}

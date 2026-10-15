<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['unit_cost' => 'decimal:2'];
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }
}

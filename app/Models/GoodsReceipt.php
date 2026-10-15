<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['total' => 'decimal:2'];
    }

    public function batches()
    {
        return $this->hasMany(MedicineBatch::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}

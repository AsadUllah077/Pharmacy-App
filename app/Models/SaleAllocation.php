<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleAllocation extends Model
{
    protected $guarded = ['id'];

    public function batch()
    {
        return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id');
    }

    public function item()
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
    }
}

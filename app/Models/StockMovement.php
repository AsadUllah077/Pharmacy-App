<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Stock movements are immutable.'));
        static::deleting(fn () => throw new \LogicException('Stock movements are immutable.'));
    }

    public function batch()
    {
        return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id');
    }
}

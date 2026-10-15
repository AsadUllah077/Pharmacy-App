<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class MedicineBatch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'date', 'unit_cost' => 'decimal:2'];
    }

    protected function expiresAt(): Attribute
    {
        return Attribute::make(set: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeSellable($query)
    {
        return $query->where('status', 'available')->where('quantity', '>', 0)->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>', now(config('pharmacy.timezone'))->toDateString()));
    }
}

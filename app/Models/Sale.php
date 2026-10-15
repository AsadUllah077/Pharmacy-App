<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return array_fill_keys(['subtotal', 'discount', 'tax', 'total', 'cost', 'change'], 'decimal:2');
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function returns()
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

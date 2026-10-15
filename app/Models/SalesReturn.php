<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    protected $guarded = ['id'];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}

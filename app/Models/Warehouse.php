<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = ['branch_id', 'name'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}

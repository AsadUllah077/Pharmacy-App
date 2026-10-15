<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = ['code', 'name', 'type'];

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }
}

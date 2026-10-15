<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $guarded = ['id'];

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Posted journals are immutable.'));
        static::deleting(fn () => throw new \LogicException('Posted journals are immutable.'));
    }
}

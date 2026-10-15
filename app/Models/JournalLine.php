<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    protected $guarded = ['id'];

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Posted journal lines are immutable.'));
        static::deleting(fn () => throw new \LogicException('Posted journal lines are immutable.'));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class AccountRestriction extends Model
{
    public const FULL_LOCK = 'full_lock';
    public const DEBIT_BLOCK = 'debit_block';
    public const CREDIT_BLOCK = 'credit_block';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['locked_at' => 'datetime', 'unlocked_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Restrictions cannot be deleted.'));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('unlocked_at');
    }
}

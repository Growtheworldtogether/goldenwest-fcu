<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/** One row per decision on anything that can be decided: transactions now, cards, loans and KYC later. */
class Decision extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['hold_until' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Decisions cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Decisions cannot be deleted.'));
    }

    public function decidable(): MorphTo
    {
        return $this->morphTo();
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}

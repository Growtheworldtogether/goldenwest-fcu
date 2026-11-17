<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CryptoTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CryptoAsset::class, 'asset_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CryptoLedgerEntry::class);
    }

    public function decisions(): MorphMany
    {
        return $this->morphMany(Decision::class, 'decidable');
    }

    public function lastDecision(): ?Decision
    {
        return $this->decisions()->latest('id')->first();
    }

    public function total(): int
    {
        return $this->amount + $this->fee;
    }

    public function statusLabel(): string
    {
        return ['pending' => 'In review', 'held' => 'On hold', 'completed' => $this->type === 'send' ? 'Sent' : 'Completed', 'rejected' => 'Declined', 'cancelled' => 'Cancelled'][$this->status] ?? $this->status;
    }
}

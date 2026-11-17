<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WireTransfer extends Model
{
    protected $guarded = [];

    protected $hidden = ['account_number'];

    protected function casts(): array
    {
        return ['process_on' => 'date', 'account_number' => 'encrypted'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isInternational(): bool
    {
        return $this->kind === 'international';
    }

    public function countryName(): ?string
    {
        return $this->country ? (config('wires.countries')[$this->country] ?? $this->country) : null;
    }

    /** Completed wires this member already sent to the same bank account, for the staff reviewer. */
    public function earlierWiresToSameAccount(): int
    {
        return static::where('user_id', $this->user_id)->where('id', '!=', $this->id)
            ->when($this->isInternational(), fn ($q) => $q->where('swift', $this->swift), fn ($q) => $q->where('routing_number', $this->routing_number))
            ->where('last4', $this->last4)
            ->whereHas('transaction', fn ($q) => $q->where('status', 'completed'))->count();
    }
}

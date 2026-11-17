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

    /** Completed wires this member already sent to the same bank account, for the staff reviewer. */
    public function earlierWiresToSameAccount(): int
    {
        return static::where('user_id', $this->user_id)->where('id', '!=', $this->id)
            ->where('routing_number', $this->routing_number)->where('last4', $this->last4)
            ->whereHas('transaction', fn ($q) => $q->where('status', 'completed'))->count();
    }
}

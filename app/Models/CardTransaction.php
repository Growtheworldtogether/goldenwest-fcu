<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'authorized_at' => 'datetime', 'settled_at' => 'datetime'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function money(): Money
    {
        return Money::cents($this->amount);
    }
}

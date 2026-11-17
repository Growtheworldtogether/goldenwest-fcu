<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    protected $guarded = [];

    protected $hidden = ['number_encrypted', 'cvv_encrypted'];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'decided_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(CardTransaction::class)->latest('id');
    }

    public function decisions()
    {
        return $this->morphMany(Decision::class, 'decidable');
    }

    public function isUsable(): bool
    {
        return $this->status === 'active';
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['active', 'frozen'], true);
    }

    public function dailyLimit(): Money
    {
        return Money::cents($this->daily_limit ?? (int) config('bank.card_daily_limit'));
    }

    public function purchaseLimit(): Money
    {
        return Money::cents($this->purchase_limit ?? (int) config('bank.card_purchase_limit'));
    }

    public function maskedNumber(): string
    {
        return '•••• •••• •••• '.$this->last4;
    }

    public function expiry(): string
    {
        return $this->expiry_month ? sprintf('%02d/%02d', $this->expiry_month, $this->expiry_year % 100) : '';
    }

    public function label(): string
    {
        return ($this->nickname ?: 'Virtual card').' ending '.$this->last4;
    }
}

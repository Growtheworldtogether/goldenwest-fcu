<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['target_date' => 'date', 'auto_next_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_account_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function target(): Money
    {
        return Money::cents($this->target_amount);
    }

    public function savedAmount(): Money
    {
        return $this->account->balance;
    }

    public function percent(): int
    {
        return $this->target_amount > 0 ? (int) min(100, floor($this->savedAmount()->cents * 100 / $this->target_amount)) : 0;
    }
}

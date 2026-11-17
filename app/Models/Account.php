<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'product_id', 'type', 'parent_account_id', 'account_number', 'nickname',
        'balance', 'available_balance', 'held_balance', 'status', 'daily_limit', 'overdraft_account_id',
    ];

    protected function casts(): array
    {
        return [
            'balance' => MoneyCast::class,
            'available_balance' => MoneyCast::class,
            'held_balance' => MoneyCast::class,
            'daily_limit' => MoneyCast::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function holders(): HasMany
    {
        return $this->hasMany(AccountHolder::class);
    }

    /** Every policy and query that asks "who owns this" reads account_holders. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_holders')->withPivot(['role', 'status']);
    }

    public function overdraftAccount(): BelongsTo
    {
        return $this->belongsTo(self::class, 'overdraft_account_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /** "••••4821" for screens and logs. The full number is never logged. */
    public function masked(): string
    {
        return '••••'.substr($this->account_number, -4);
    }
}

<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

class Transaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'status' => TransactionStatus::class,
            'amount' => MoneyCast::class,
            'fee' => MoneyCast::class,
            'effective_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Money records are never hard-deleted. Corrections are new reversal transactions.
        static::deleting(fn () => throw new LogicException('Transactions cannot be deleted.'));
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /** The memo a member may read: only one they typed themselves. A staff note, reason or history marker stays internal. */
    public function memberMemo(): ?string
    {
        if (! $this->memo || ($this->initiated_by && $this->initiator?->isAdmin())) {
            return null;
        }

        return $this->memo;
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function decisions(): MorphMany
    {
        return $this->morphMany(Decision::class, 'decidable');
    }

    /** Amount plus fee: what the sending account pays. */
    public function totalDebit(): Money
    {
        return $this->amount->add($this->fee);
    }
}

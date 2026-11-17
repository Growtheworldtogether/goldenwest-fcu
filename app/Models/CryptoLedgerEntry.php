<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only, like the cash ledger. Wallet balances are a cache of these rows. */
class CryptoLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Crypto ledger entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Crypto ledger entries are append-only.'));
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(CryptoTransaction::class, 'crypto_transaction_id');
    }
}

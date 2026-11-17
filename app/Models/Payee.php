<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payee extends Model
{
    protected $guarded = [];

    protected $hidden = ['account_number'];

    protected function casts(): array
    {
        return ['account_number' => 'encrypted', 'activates_at' => 'datetime', 'is_biller' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Usable once the cooling-off period has passed. */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->activates_at->lte(now());
    }

    public function label(): string
    {
        return ($this->bank_name ?: $this->name).' ending '.$this->last4;
    }
}

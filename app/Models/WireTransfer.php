<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WireTransfer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['process_on' => 'date'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(Payee::class);
    }
}

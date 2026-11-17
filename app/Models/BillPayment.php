<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillPayment extends Model
{
    public const STATUSES = ['scheduled' => 'Scheduled', 'review' => 'Under review', 'processing' => 'On its way', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'failed' => 'Could not be sent'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['send_date' => 'date', 'estimated_delivery' => 'date'];
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(Biller::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function money(): Money
    {
        return Money::cents($this->amount);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}

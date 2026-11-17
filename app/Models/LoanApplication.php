<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanApplication extends Model
{
    public const STATUSES = ['submitted' => 'Submitted', 'in_review' => 'In review', 'needs_info' => 'We need more information', 'approved' => 'Approved', 'rejected' => 'Not approved', 'withdrawn' => 'Withdrawn'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function depositAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deposit_account_id');
    }

    public function decisions()
    {
        return $this->morphMany(Decision::class, 'decidable');
    }

    public function loan()
    {
        return $this->hasOne(Loan::class);
    }

    public function money(): Money
    {
        return Money::cents($this->amount);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['submitted', 'in_review', 'needs_info'], true);
    }
}

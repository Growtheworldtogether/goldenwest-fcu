<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanInstallment extends Model
{
    protected $table = 'loan_schedule';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'paid_at' => 'datetime'];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** Principal plus interest, the level payment. */
    public function payment(): Money
    {
        return Money::cents($this->principal + $this->interest);
    }

    /** What it takes to clear this installment, including any late fee. */
    public function amountDue(): Money
    {
        return Money::cents($this->principal + $this->interest + $this->late_fee);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}

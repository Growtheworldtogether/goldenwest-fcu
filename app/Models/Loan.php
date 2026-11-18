<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['revolving' => 'boolean', 'last_billed_on' => 'date', 'first_due_date' => 'date', 'next_due_date' => 'date', 'disbursed_at' => 'datetime', 'paid_off_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function autopayAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'autopay_account_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function schedule(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('installment');
    }

    public function balanceMoney(): Money
    {
        return Money::cents($this->balance);
    }

    public function principalMoney(): Money
    {
        return Money::cents($this->principal);
    }

    public function apr(): string
    {
        return number_format($this->apr_bps / 100, 2).'%';
    }

    /** What can still be drawn on a line of credit. */
    public function availableCredit(): Money
    {
        return Money::cents(max(0, $this->principal - $this->balance));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['active', 'delinquent'], true);
    }

    public function label(): string
    {
        return $this->product->name.' '.$this->reference;
    }
}

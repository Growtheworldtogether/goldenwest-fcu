<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class LoanProduct extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['terms' => 'array', 'active' => 'boolean'];
    }

    public function minAmount(): Money
    {
        return Money::cents($this->min_amount);
    }

    public function maxAmount(): Money
    {
        return Money::cents($this->max_amount);
    }

    public function aprRange(): string
    {
        return number_format($this->apr_min_bps / 100, 2).'% to '.number_format($this->apr_max_bps / 100, 2).'% APR';
    }
}

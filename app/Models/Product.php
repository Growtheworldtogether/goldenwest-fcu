<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['code', 'name', 'kind', 'dividend_rate_bps', 'min_balance', 'monthly_fee'];

    protected function casts(): array
    {
        return ['min_balance' => MoneyCast::class, 'monthly_fee' => MoneyCast::class];
    }
}

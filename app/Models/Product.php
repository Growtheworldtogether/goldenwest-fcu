<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['code', 'name', 'kind', 'segment', 'dividend_rate_bps', 'min_balance', 'monthly_fee'];

    public function isBusiness(): bool
    {
        return $this->segment === 'business';
    }

    protected function casts(): array
    {
        return ['min_balance' => MoneyCast::class, 'monthly_fee' => MoneyCast::class];
    }
}

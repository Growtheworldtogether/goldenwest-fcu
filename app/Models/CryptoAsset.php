<?php

namespace App\Models;

use App\Support\CryptoAmount;
use Illuminate\Database\Eloquent\Model;

class CryptoAsset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function label(): string
    {
        return $this->name.' ('.$this->symbol.($this->network !== $this->name ? ', '.$this->network : '').')';
    }

    public function format(int $units, bool $symbol = true): string
    {
        return CryptoAmount::format($units, $this->decimals).($symbol ? ' '.$this->symbol : '');
    }

    /** A rough US dollar value in cents, or null when staff have not set a rate. Estimates only. */
    public function usdCents(int $units): ?int
    {
        return $this->usd_rate_cents === null ? null : (int) round($units * $this->usd_rate_cents / (10 ** $this->decimals));
    }
}

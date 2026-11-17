<?php

namespace App\Models;

use App\Support\CryptoAmount;
use Illuminate\Database\Eloquent\Model;

class CryptoAsset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'live_rate' => 'boolean', 'rate_updated_at' => 'datetime'];
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

    public function hasRate(): bool
    {
        return $this->usd_rate_cents !== null && $this->usd_rate_cents > 0;
    }

    /** What a member sees: dollars. Falls back to the coin amount only when staff have not set a rate. */
    public function usd(int $units): string
    {
        return $this->hasRate() ? '$'.number_format($this->usdCents($units) / 100, 2) : $this->format($units);
    }

    /** Dollars typed by a member, as whole coin units at the current rate. Without a rate the text is read as coins. */
    public function unitsFromInput(string $input): int
    {
        if (! $this->hasRate()) {
            return CryptoAmount::parse($input, $this->decimals);
        }
        $cents = \App\Support\Money::parse($input)->cents;
        $units = intdiv($cents * (10 ** $this->decimals), $this->usd_rate_cents);
        if ($units <= 0) {
            throw new \InvalidArgumentException('That amount is too small.');
        }

        return $units;
    }
}

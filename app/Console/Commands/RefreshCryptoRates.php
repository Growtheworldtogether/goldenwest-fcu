<?php

namespace App\Console\Commands;

use App\Services\CryptoRates;
use Illuminate\Console\Command;

class RefreshCryptoRates extends Command
{
    protected $signature = 'crypto:rates';

    protected $description = 'Fetch live crypto prices in US dollars';

    public function handle(CryptoRates $rates): int
    {
        $n = $rates->refresh();
        $this->info($n.' price'.($n === 1 ? '' : 's').' updated.');

        return $n > 0 ? self::SUCCESS : self::FAILURE;
    }
}

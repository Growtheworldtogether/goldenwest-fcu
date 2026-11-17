<?php

namespace App\Services;

use App\Models\CryptoAsset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Live dollar prices from CoinGecko's public price endpoint. Never throws: a failed call keeps the last known rates. */
class CryptoRates
{
    public const SOURCE = 'CoinGecko';

    /** Fetches prices for every asset set to use a live rate. Returns how many were updated. */
    public function refresh(): int
    {
        $assets = CryptoAsset::where('live_rate', true)->whereNotNull('coingecko_id')->get();
        if ($assets->isEmpty()) {
            return 0;
        }
        try {
            $request = Http::timeout(config('crypto.rates.timeout'))->acceptJson();
            if ($key = config('crypto.rates.key')) {
                $request = $request->withHeaders(['x-cg-demo-api-key' => $key]);
            }
            $response = $request->get(config('crypto.rates.url'), ['ids' => $assets->pluck('coingecko_id')->unique()->implode(','), 'vs_currencies' => 'usd']);
            if (! $response->successful()) {
                Log::warning('Crypto price fetch failed', ['status' => $response->status()]);

                return 0;
            }
            $prices = $response->json();
        } catch (Throwable $e) {
            Log::warning('Crypto price fetch failed', ['error' => $e->getMessage()]);

            return 0;
        }

        $updated = 0;
        foreach ($assets as $asset) {
            $usd = $prices[$asset->coingecko_id]['usd'] ?? null;
            if (! is_numeric($usd) || $usd <= 0) {
                continue;
            }
            $asset->update(['usd_rate_cents' => (int) round($usd * 100), 'rate_updated_at' => now()]);
            $updated++;
        }

        return $updated;
    }

    /** Called from member pages. Tries at most once a minute, even when the call fails, so a slow service never slows the site. */
    public function refreshIfStale(): void
    {
        $oldest = CryptoAsset::where('live_rate', true)->whereNotNull('coingecko_id')->where('enabled', true)->min('rate_updated_at');
        if ($oldest !== null && Carbon::parse($oldest)->gt(now()->subMinutes(config('crypto.rates.stale_minutes')))) {
            return;
        }
        if (Cache::add('crypto.rates.attempt', 1, 60)) {
            $this->refresh();
        }
    }

    public static function lastUpdated(): ?Carbon
    {
        $at = CryptoAsset::where('live_rate', true)->max('rate_updated_at');

        return $at ? Carbon::parse($at) : null;
    }
}

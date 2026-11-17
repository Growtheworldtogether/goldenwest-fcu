<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crypto_assets', function (Blueprint $table) {
            $table->string('coingecko_id', 60)->nullable()->after('usd_rate_cents');
            $table->boolean('live_rate')->default(true)->after('coingecko_id');
            $table->timestamp('rate_updated_at')->nullable()->after('live_rate');
        });

        foreach (['BTC' => 'bitcoin', 'ETH' => 'ethereum', 'USDC' => 'usd-coin', 'USDT' => 'tether', 'LTC' => 'litecoin', 'SOL' => 'solana'] as $symbol => $id) {
            // The starting rates count as fresh, so nothing calls out until they are ten minutes old.
            DB::table('crypto_assets')->where('symbol', $symbol)->update(['coingecko_id' => $id, 'rate_updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('crypto_assets', fn (Blueprint $table) => $table->dropColumn(['coingecko_id', 'live_rate', 'rate_updated_at']));
    }
};

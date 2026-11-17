<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_assets', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 10);
            $table->string('name', 40);
            $table->string('network', 20);
            $table->unsignedTinyInteger('decimals');
            $table->unsignedBigInteger('network_fee')->default(0); // base units, charged on every send
            $table->unsignedBigInteger('usd_rate_cents')->nullable(); // cents per whole unit, for estimates only
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['symbol', 'network']);
        });

        Schema::create('crypto_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('asset_id')->constrained('crypto_assets');
            $table->unsignedBigInteger('balance')->default(0);
            $table->unsignedBigInteger('held')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'asset_id']);
        });

        Schema::create('crypto_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('asset_id')->constrained('crypto_assets');
            $table->string('network', 20);
            $table->string('address', 120)->nullable(); // empty while the member's request waits for staff
            $table->string('memo', 60)->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users');
            $table->string('status', 10)->default('requested'); // requested, active, retired
            $table->timestamps();
            $table->index(['user_id', 'asset_id', 'status']);
        });

        Schema::create('crypto_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('asset_id')->constrained('crypto_assets');
            $table->string('type', 12); // fund, send, adjustment
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee')->default(0);
            $table->string('address', 120)->nullable();
            $table->string('memo', 60)->nullable();
            $table->string('tx_hash', 100)->nullable();
            $table->string('status', 10)->default('pending'); // pending, held, completed, rejected, cancelled
            $table->string('description', 140)->nullable();
            $table->string('idempotency_key', 40)->nullable()->unique();
            $table->foreignId('impersonator_id')->nullable()->constrained('users');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('crypto_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crypto_transaction_id')->constrained('crypto_transactions');
            $table->foreignId('wallet_id')->constrained('crypto_wallets');
            $table->string('direction', 6); // credit, debit
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->timestamp('created_at')->useCurrent();
        });

        $now = now();
        foreach ([
            ['BTC', 'Bitcoin', 'Bitcoin', 8, 5_000, 9_800_000],
            ['ETH', 'Ether', 'Ethereum', 8, 50_000, 350_000],
            ['USDC', 'USD Coin', 'Ethereum', 6, 1_000_000, 100],
            ['USDT', 'Tether', 'Tron', 6, 1_000_000, 100],
            ['LTC', 'Litecoin', 'Litecoin', 8, 100_000, 9_000],
            ['SOL', 'Solana', 'Solana', 8, 5_000, 18_000],
        ] as [$symbol, $name, $network, $decimals, $fee, $rate]) {
            DB::table('crypto_assets')->insert(['symbol' => $symbol, 'name' => $name, 'network' => $network, 'decimals' => $decimals, 'network_fee' => $fee, 'usd_rate_cents' => $rate, 'enabled' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_ledger_entries');
        Schema::dropIfExists('crypto_transactions');
        Schema::dropIfExists('crypto_addresses');
        Schema::dropIfExists('crypto_wallets');
        Schema::dropIfExists('crypto_assets');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('kind', 20); // share_savings, share_draft, money_market, certificate
            $table->unsignedInteger('dividend_rate_bps')->default(0); // basis points, 355 = 3.55%
            $table->unsignedBigInteger('min_balance')->default(0);
            $table->unsignedBigInteger('monthly_fee')->default(0);
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->string('type', 10)->default('standard'); // standard or goal
            $table->foreignId('parent_account_id')->nullable()->constrained('accounts');
            $table->string('account_number', 12)->unique();
            $table->string('nickname')->nullable();
            $table->unsignedBigInteger('balance')->default(0);
            $table->unsignedBigInteger('available_balance')->default(0);
            $table->unsignedBigInteger('held_balance')->default(0);
            $table->string('status', 20)->default('active'); // active, closed
            $table->unsignedBigInteger('daily_limit')->nullable();
            $table->foreignId('overdraft_account_id')->nullable()->constrained('accounts');
            $table->timestamps();
        });

        Schema::create('account_holders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('user_id')->constrained('users');
            $table->string('role', 10)->default('primary'); // primary or joint
            $table->string('status', 20)->default('active');
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();
            $table->unique(['account_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_holders');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('products');
    }
};

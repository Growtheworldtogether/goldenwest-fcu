<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('account_id')->constrained('accounts');
            $table->string('status', 20)->default('requested'); // requested, active, frozen, cancelled, rejected
            $table->string('nickname', 40)->nullable();
            $table->unsignedBigInteger('serial')->nullable()->unique();
            $table->string('last4', 4)->nullable();
            $table->text('number_encrypted')->nullable();
            $table->text('cvv_encrypted')->nullable();
            $table->unsignedTinyInteger('expiry_month')->nullable();
            $table->unsignedSmallInteger('expiry_year')->nullable();
            $table->unsignedBigInteger('daily_limit')->nullable();
            $table->unsignedBigInteger('purchase_limit')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('replaces_card_id')->nullable()->constrained('cards');
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('card_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('cards');
            $table->string('merchant', 80);
            $table->unsignedBigInteger('amount');
            $table->string('status', 20); // authorized, settled, declined, released
            $table->string('decline_reason', 40)->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions');
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->index(['card_id', 'status', 'created_at']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->text('body');
            $table->string('type', 12)->default('info'); // info, warning, maintenance
            $table->string('audience', 30)->default('everyone'); // everyone or a feature key
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('dismissible')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('announcement_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_dismissals');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('card_transactions');
        Schema::dropIfExists('cards');
    }
};

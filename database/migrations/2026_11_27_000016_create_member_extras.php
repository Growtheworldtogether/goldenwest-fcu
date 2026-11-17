<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('parent_account_id')->constrained('accounts');
            $table->foreignId('account_id')->constrained('accounts'); // the hidden sub-account that holds the money
            $table->string('name', 60);
            $table->unsignedBigInteger('target_amount');
            $table->date('target_date')->nullable();
            $table->string('status', 10)->default('active'); // active, achieved, closed
            $table->unsignedBigInteger('auto_amount')->nullable();
            $table->string('auto_frequency', 8)->nullable(); // weekly, monthly
            $table->date('auto_next_date')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('billers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('name', 80);
            $table->string('category', 20);
            $table->string('account_reference', 40);
            $table->string('status', 10)->default('active'); // active, removed
            $table->timestamps();
        });

        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('biller_id')->constrained('billers');
            $table->foreignId('account_id')->constrained('accounts');
            $table->unsignedBigInteger('amount');
            $table->date('send_date');
            $table->date('estimated_delivery')->nullable();
            $table->string('recurrence', 8)->default('none'); // none, weekly, monthly
            $table->string('status', 12)->default('scheduled'); // scheduled, review, processing, delivered, cancelled, failed
            $table->string('failure', 200)->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions');
            $table->timestamps();
            $table->index(['status', 'send_date']);
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('category', 20);
            $table->unsignedBigInteger('monthly_limit');
            $table->timestamps();
            $table->unique(['user_id', 'category']);
        });

        Schema::create('transaction_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('transaction_id')->constrained('transactions');
            $table->string('category', 20);
            $table->timestamps();
            $table->unique(['user_id', 'transaction_id']);
        });

        Schema::create('spending_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->char('month', 7); // 2026-11
            $table->string('category', 20);
            $table->unsignedBigInteger('total');
            $table->unsignedInteger('count');
            $table->timestamps();
            $table->unique(['user_id', 'month', 'category']);
        });
    }

    public function down(): void
    {
        foreach (['spending_summaries', 'transaction_categories', 'budgets', 'bill_payments', 'billers', 'goals'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->char('reference', 26)->unique(); // ULID
            $table->string('idempotency_key', 120)->unique();
            $table->string('type', 30)->index();
            // A null side means money enters or leaves the credit union (cash, ACH, a loan fund).
            $table->foreignId('from_account_id')->nullable()->constrained('accounts');
            $table->foreignId('to_account_id')->nullable()->constrained('accounts');
            $table->unsignedBigInteger('payee_id')->nullable();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee')->default(0);
            $table->string('status', 20)->index();
            $table->foreignId('initiated_by')->nullable()->constrained('users');
            $table->foreignId('impersonator_id')->nullable()->constrained('users');
            $table->foreignId('related_transaction_id')->nullable()->constrained('transactions');
            $table->date('effective_date');
            $table->string('memo')->nullable();
            $table->string('member_description')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['from_account_id', 'effective_date']);
        });

        // Append-only. Balances are a cache of this table.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions');
            $table->foreignId('account_id')->constrained('accounts');
            $table->string('direction', 6); // debit or credit
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->timestamps();
            $table->index(['account_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('transactions');
    }
};

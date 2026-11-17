<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optional features a member has been given: wires now, cards, crypto and loans later.
        Schema::create('user_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('feature', 30);
            $table->foreignId('enabled_by')->nullable()->constrained('users');
            $table->timestamp('enabled_at');
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('disabled_by')->nullable()->constrained('users');
            $table->string('note', 200)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'feature', 'disabled_at']);
        });

        // The wire-specific details. The money, status and decisions live on the transaction.
        Schema::create('wire_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('transaction_id')->unique()->constrained('transactions');
            $table->foreignId('payee_id')->constrained('payees');
            $table->string('recipient_address', 200);
            $table->string('purpose', 140);
            $table->date('process_on');
            $table->timestamps();
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wire_transfers');
        Schema::dropIfExists('user_features');
    }
};

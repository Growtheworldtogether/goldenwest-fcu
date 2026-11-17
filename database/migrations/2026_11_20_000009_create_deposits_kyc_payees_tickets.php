<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // External accounts a member sends money to or pulls money from. Usable after a cooling-off period.
        Schema::create('payees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('name', 120);
            $table->string('bank_name', 120)->nullable();
            $table->char('routing_number', 9);
            $table->text('account_number'); // encrypted
            $table->char('last4', 4);
            $table->string('account_type', 10)->default('checking');
            $table->boolean('is_biller')->default(false);
            $table->string('biller_category', 40)->nullable();
            $table->timestamp('activates_at');
            $table->string('status', 10)->default('active'); // active or removed
            $table->foreignId('waived_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        // One row per deposit. The money itself, its status and its decisions live on the transaction.
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('transaction_id')->nullable()->unique()->constrained('transactions');
            $table->string('method', 10); // check, ach or cash
            $table->foreignId('payee_id')->nullable()->constrained('payees');
            $table->string('check_number', 12)->nullable();
            $table->string('front_path')->nullable();
            $table->string('back_path')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users'); // the teller, for cash
            $table->timestamps();
            $table->index(['user_id', 'id']);
        });

        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('type', 30); // government_id or proof_of_address
            $table->string('path');
            $table->string('original_name', 120);
            $table->string('mime', 40);
            $table->unsignedInteger('size');
            $table->string('status', 12)->default('pending'); // pending, approved, rejected, replaced
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type', 'status']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 12)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('category', 20);
            $table->string('subject', 160);
            $table->string('status', 20)->default('open'); // open, awaiting_member, awaiting_staff, resolved, closed
            $table->string('priority', 10)->default('normal');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions');
            $table->foreignId('account_id')->nullable()->constrained('accounts');
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets');
            $table->foreignId('author_id')->constrained('users');
            $table->text('body');
            $table->boolean('is_internal_note')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('kyc_documents');
        Schema::dropIfExists('deposits');
        Schema::dropIfExists('payees');
    }
};

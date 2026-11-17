<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A wire is its own form with its own recipient details, not a saved ACH account.
// wire_transfers was added one step earlier, so it is rebuilt with a snapshot of the recipient on each wire.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('wire_transfers');

        Schema::create('wire_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('name', 120);
            $table->string('address', 200);
            $table->string('bank_name', 120);
            $table->char('routing_number', 9);
            $table->text('account_number'); // encrypted
            $table->char('last4', 4);
            $table->string('status', 10)->default('active'); // active or removed
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('wire_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('transaction_id')->unique()->constrained('transactions');
            $table->foreignId('recipient_id')->nullable()->constrained('wire_recipients');
            $table->string('recipient_name', 120);
            $table->string('recipient_address', 200);
            $table->string('bank_name', 120);
            $table->char('routing_number', 9);
            $table->text('account_number'); // encrypted copy, as typed for this wire
            $table->char('last4', 4);
            $table->string('purpose', 140);
            $table->string('message', 140)->nullable(); // note to the recipient
            $table->date('process_on');
            $table->timestamps();
            $table->index(['user_id', 'id']);
            $table->index(['user_id', 'routing_number', 'last4']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wire_transfers');
        Schema::dropIfExists('wire_recipients');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reason_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label');
            $table->string('applies_to', 40)->default('all');
            $table->timestamps();
        });

        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->morphs('decidable');
            $table->string('decision', 12); // approved, rejected, held, released
            $table->string('reason_code', 40)->nullable();
            $table->text('reason_text')->nullable();
            $table->foreignId('decided_by')->constrained('users');
            $table->timestamp('hold_until')->nullable();
            $table->timestamps();
        });

        Schema::create('account_restrictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('accounts');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('type', 20); // full_lock, debit_block, credit_block
            $table->string('reason_code', 40);
            $table->text('reason_text')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users');
            $table->timestamp('locked_at');
            $table->foreignId('unlocked_by')->nullable()->constrained('users');
            $table->timestamp('unlocked_at')->nullable();
            $table->text('unlock_note')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'unlocked_at']);
            $table->index(['user_id', 'unlocked_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->foreignId('impersonator_id')->nullable()->constrained('users');
            $table->string('action', 60)->index();
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('old')->nullable();
            $table->json('new')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('account_restrictions');
        Schema::dropIfExists('decisions');
        Schema::dropIfExists('reason_codes');
    }
};

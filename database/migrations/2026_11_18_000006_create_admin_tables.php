<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title', 30);
            $table->timestamps();
        });

        Schema::create('impersonations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users');
            $table->foreignId('member_id')->constrained('users');
            $table->string('reason_code', 40);
            $table->text('reason_text')->nullable();
            $table->string('ticket_reference', 60)->nullable();
            $table->string('mode', 20)->default('full');
            $table->string('ip', 45)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('ended_reason', 20)->nullable(); // exited, expired
            $table->timestamps();
            $table->index(['member_id', 'started_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handled_by');
            $table->dropColumn('handled_at');
        });
        Schema::dropIfExists('settings');
        Schema::dropIfExists('impersonations');
        Schema::dropIfExists('admin_profiles');
    }
};

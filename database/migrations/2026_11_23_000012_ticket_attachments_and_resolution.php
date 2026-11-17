<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets');
            $table->foreignId('message_id')->constrained('ticket_messages');
            $table->string('path');
            $table->string('name', 120);
            $table->string('mime', 40);
            $table->unsignedInteger('size');
            $table->timestamps();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('resolved_at')->nullable()->after('last_reply_at');
            $table->index(['status', 'last_reply_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['status', 'last_reply_at']);
            $table->dropColumn('resolved_at');
        });
        Schema::dropIfExists('ticket_attachments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Audit rows are never deleted. When a member who never used their account is erased, their rows stay and lose only the link to the user. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->dropForeign(['actor_id']);
            $t->dropForeign(['impersonator_id']);
        });
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $t->foreign('impersonator_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->dropForeign(['actor_id']);
            $t->dropForeign(['impersonator_id']);
        });
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->foreign('actor_id')->references('id')->on('users');
            $t->foreign('impersonator_id')->references('id')->on('users');
        });
    }
};

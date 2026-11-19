<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_security_answers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('question_key', 40);
            $t->string('answer_hash');
            $t->timestamps();
            $t->unique(['user_id', 'question_key']);
        });

        Schema::create('login_ips', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('ip', 45);
            $t->timestamp('first_seen_at');
            $t->timestamp('last_seen_at');
            $t->unique(['user_id', 'ip']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('security_last_question', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('security_last_question'));
        Schema::dropIfExists('login_ips');
        Schema::dropIfExists('user_security_answers');
    }
};

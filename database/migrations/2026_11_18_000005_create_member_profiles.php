<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('field_of_membership', 30);
            $table->string('eligibility_state', 2)->nullable();
            $table->string('referred_by_member_number', 12)->nullable();
            $table->date('membership_opened_at');
            $table->text('ssn_last4')->nullable(); // encrypted cast
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('notification_preferences'));
        Schema::dropIfExists('member_profiles');
    }
};

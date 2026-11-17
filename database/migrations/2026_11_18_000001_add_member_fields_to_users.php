<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 10)->default('user')->after('password');
            $table->string('member_number', 12)->nullable()->unique()->after('role');
            $table->string('first_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();
            $table->string('phone', 30)->nullable();
            $table->date('dob')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('state', 2)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('status', 20)->default('active');
            $table->string('kyc_status', 20)->default('pending');
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'member_number', 'first_name', 'last_name', 'phone', 'dob', 'address_line1', 'city', 'state', 'postal_code', 'status', 'kyc_status', 'locked_until', 'last_login_at']);
        });
    }
};

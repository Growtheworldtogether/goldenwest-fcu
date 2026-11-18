<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->string('status', 10)->default('approved')->after('user_id'); // pending until the business document is approved, then approved
            $table->string('requested_accounts', 10)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('business_profiles', fn (Blueprint $t) => $t->dropColumn(['status', 'requested_accounts']));
    }
};

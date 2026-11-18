<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_settings', function (Blueprint $t) {
            $t->string('routing_number', 9)->nullable()->after('support_hours');
            $t->string('nmls_number', 12)->nullable()->after('routing_number');
        });

        // Rows saved before these settings existed keep showing what the site showed before.
        DB::table('brand_settings')->update(['routing_number' => '121478906', 'nmls_number' => '482915']);
    }

    public function down(): void
    {
        Schema::table('brand_settings', fn (Blueprint $t) => $t->dropColumn(['routing_number', 'nmls_number']));
    }
};

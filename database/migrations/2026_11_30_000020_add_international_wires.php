<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['wire_recipients', 'wire_transfers'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('kind', 13)->default('domestic'); // domestic or international
                $t->char('country', 2)->nullable();
                $t->string('swift', 11)->nullable();
            });
            Schema::table($table, fn (Blueprint $t) => $t->char('routing_number', 9)->nullable()->change());
        }
    }

    public function down(): void
    {
        foreach (['wire_recipients', 'wire_transfers'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['kind', 'country', 'swift']));
        }
    }
};

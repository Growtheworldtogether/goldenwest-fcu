<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->string('segment', 10)->default('personal')->after('type'); // personal, business
            $table->boolean('revolving')->default(false)->after('segment');
        });
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->string('collateral', 120)->nullable();
            $table->unsignedBigInteger('collateral_value')->nullable();
        });
        Schema::table('loans', function (Blueprint $table) {
            $table->boolean('revolving')->default(false);
            $table->date('last_billed_on')->nullable();
        });

        $now = now();
        foreach ([
            ['BUS-TERM', 'bterm', 'Business term loan', 725, 1350, 500_000, 50_000_000, [12, 24, 36, 48, 60, 84], false],
            ['BUS-EQUIP', 'bequip', 'Equipment loan', 650, 1199, 500_000, 75_000_000, [24, 36, 48, 60, 84], false],
            ['BUS-LOC', 'bloc', 'Business line of credit', 900, 1500, 1_000_000, 25_000_000, [12], true],
        ] as [$code, $type, $name, $min, $max, $lo, $hi, $terms, $revolving]) {
            if (! DB::table('loan_products')->where('code', $code)->exists()) {
                DB::table('loan_products')->insert(['code' => $code, 'type' => $type, 'segment' => 'business', 'revolving' => $revolving, 'name' => $name, 'apr_min_bps' => $min, 'apr_max_bps' => $max, 'min_amount' => $lo, 'max_amount' => $hi, 'terms' => json_encode($terms), 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        DB::table('loan_products')->where('segment', 'business')->delete();
        Schema::table('loans', fn (Blueprint $t) => $t->dropColumn(['revolving', 'last_billed_on']));
        Schema::table('loan_applications', fn (Blueprint $t) => $t->dropColumn(['collateral', 'collateral_value']));
        Schema::table('loan_products', fn (Blueprint $t) => $t->dropColumn(['segment', 'revolving']));
    }
};

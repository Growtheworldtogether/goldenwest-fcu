<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('segment', 10)->default('personal')->after('kind'); // personal, business
        });

        Schema::create('business_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('legal_name', 120);
            $table->string('entity_type', 20); // sole_proprietor, llc, corporation, partnership, nonprofit
            $table->text('ein'); // encrypted
            $table->string('industry', 80);
            $table->unsignedSmallInteger('started_year');
            $table->string('address_line1', 160);
            $table->string('city', 80);
            $table->string('state', 2);
            $table->string('postal_code', 10);
            $table->timestamps();
        });

        $now = now();
        foreach ([
            ['BUSINESS-CHECKING', 'Business Checking', 'share_draft', 5],
            ['BUSINESS-SAVINGS', 'Business Savings', 'share_savings', 300],
        ] as [$code, $name, $kind, $bps]) {
            if (! DB::table('products')->where('code', $code)->exists()) {
                DB::table('products')->insert(['code' => $code, 'name' => $name, 'kind' => $kind, 'segment' => 'business', 'dividend_rate_bps' => $bps, 'min_balance' => 0, 'monthly_fee' => 0, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('segment'));
    }
};

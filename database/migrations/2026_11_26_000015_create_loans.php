<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('type', 10); // personal, auto
            $table->string('name');
            $table->unsignedInteger('apr_min_bps');
            $table->unsignedInteger('apr_max_bps');
            $table->unsignedBigInteger('min_amount');
            $table->unsignedBigInteger('max_amount');
            $table->json('terms');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 12)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('loan_product_id')->constrained('loan_products');
            $table->unsignedBigInteger('amount');
            $table->unsignedSmallInteger('term_months');
            $table->string('purpose', 120);
            $table->unsignedBigInteger('annual_income');
            $table->string('employment', 20);
            $table->string('vehicle_year', 4)->nullable();
            $table->string('vehicle_make', 40)->nullable();
            $table->string('vehicle_model', 40)->nullable();
            $table->unsignedBigInteger('vehicle_value')->nullable();
            $table->foreignId('deposit_account_id')->constrained('accounts');
            $table->string('status', 20)->default('submitted'); // submitted, in_review, needs_info, approved, rejected, withdrawn
            $table->text('info_request')->nullable();
            $table->text('member_reply')->nullable();
            $table->unsignedInteger('approved_apr_bps')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 12)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('loan_application_id')->constrained('loan_applications');
            $table->foreignId('loan_product_id')->constrained('loan_products');
            $table->foreignId('account_id')->constrained('accounts'); // disbursed to
            $table->unsignedBigInteger('principal');
            $table->unsignedBigInteger('balance');
            $table->unsignedInteger('apr_bps');
            $table->unsignedSmallInteger('term_months');
            $table->string('status', 20)->default('active'); // active, delinquent, paid_off
            $table->date('first_due_date');
            $table->date('next_due_date')->nullable();
            $table->foreignId('autopay_account_id')->nullable()->constrained('accounts');
            $table->timestamp('disbursed_at');
            $table->timestamp('paid_off_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_due_date']);
        });

        Schema::create('loan_schedule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans');
            $table->unsignedSmallInteger('installment');
            $table->date('due_date');
            $table->unsignedBigInteger('principal');
            $table->unsignedBigInteger('interest');
            $table->unsignedBigInteger('balance_after');
            $table->unsignedBigInteger('late_fee')->default(0);
            $table->string('status', 10)->default('due'); // due, paid
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions');
            $table->timestamps();
            $table->index(['loan_id', 'status', 'due_date']);
        });

        $now = now();
        DB::table('loan_products')->insert([
            ['code' => 'PERSONAL', 'type' => 'personal', 'name' => 'Personal loan', 'apr_min_bps' => 899, 'apr_max_bps' => 1699, 'min_amount' => 100_000, 'max_amount' => 5_000_000, 'terms' => json_encode([12, 24, 36, 48, 60]), 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'AUTO', 'type' => 'auto', 'name' => 'Auto loan', 'apr_min_bps' => 549, 'apr_max_bps' => 1199, 'min_amount' => 500_000, 'max_amount' => 7_500_000, 'terms' => json_encode([36, 48, 60, 72]), 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_schedule');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('loan_applications');
        Schema::dropIfExists('loan_products');
    }
};

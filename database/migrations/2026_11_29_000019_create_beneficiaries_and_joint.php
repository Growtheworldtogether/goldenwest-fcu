<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->string('name', 120);
            $table->string('relationship', 30);
            $table->date('dob');
            $table->unsignedTinyInteger('share_percent');
            $table->string('tier', 12); // primary, contingent
            $table->string('status', 10)->default('active'); // active, removed (kept as history)
            $table->foreignId('added_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['account_id', 'status']);
        });

        Schema::create('joint_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 12)->unique();
            $table->string('type', 10); // invite, removal
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('subject_user_id')->nullable()->constrained('users'); // the invitee, or the holder to remove
            $table->string('invitee_email', 190)->nullable();
            $table->string('message', 200)->nullable();
            // invite: invited, review, approved, rejected, declined, cancelled. removal: consent, done, declined, cancelled.
            $table->string('status', 12);
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'type']);
        });

        Schema::table('account_holders', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deceased_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('deceased_at'));
        Schema::table('account_holders', fn (Blueprint $table) => $table->dropColumn('removed_at'));
        Schema::dropIfExists('joint_requests');
        Schema::dropIfExists('beneficiaries');
    }
};

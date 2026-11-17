<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_card_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('cards');
            $table->foreignId('user_id')->constrained('users');
            $table->string('status', 20)->default('requested'); // requested, shipped, rejected, cancelled
            $table->string('name_on_card', 40);
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city', 80);
            $table->string('state', 2);
            $table->string('postal_code', 10);
            $table->timestamp('shipped_at')->nullable();
            $table->string('decline_note', 500)->nullable();
            $table->timestamps();
            $table->index(['card_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_card_orders');
    }
};

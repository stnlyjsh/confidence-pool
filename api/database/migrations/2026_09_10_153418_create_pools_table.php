<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('season_year');
            $table->foreignId('commissioner_user_id')->constrained('users');
            $table->unsignedInteger('buy_in_amount_cents')->default(0);
            $table->unsignedInteger('weekly_payout_cents')->default(0);
            $table->unsignedInteger('season_payout_cents')->default(0);
            $table->string('invite_code')->unique();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pools');
    }
};

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
        Schema::create('picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('picked_team_id')->nullable()->constrained('teams');
            $table->unsignedTinyInteger('confidence_value');
            $table->unsignedSmallInteger('season');
            $table->unsignedTinyInteger('week');
            $table->boolean('is_auto_assigned')->default(false);
            $table->boolean('is_correct')->nullable();
            $table->unsignedTinyInteger('points_earned')->nullable();
            $table->timestamps();

            $table->unique(['pool_id', 'user_id', 'game_id']);
            $table->unique(['pool_id', 'user_id', 'season', 'week', 'confidence_value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('picks');
    }
};

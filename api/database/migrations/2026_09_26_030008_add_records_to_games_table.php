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
        // Each team's win-loss record entering this specific game — a
        // per-game snapshot, not a team attribute, since it changes every
        // week of the season.
        Schema::table('games', function (Blueprint $table) {
            $table->string('home_team_record')->nullable()->after('home_score');
            $table->string('away_team_record')->nullable()->after('away_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['home_team_record', 'away_team_record']);
        });
    }
};

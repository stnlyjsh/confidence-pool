<?php

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'espn_event_id' => (string) $this->faker->unique()->numberBetween(100000000, 999999999),
            'season' => now()->year,
            'week' => 1,
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'kickoff_at' => now()->addWeek(),
            'home_score' => null,
            'away_score' => null,
            'status' => GameStatus::Scheduled,
            'raw_espn_payload' => null,
        ];
    }

    public function final(int $homeScore = 24, int $awayScore = 17): static
    {
        return $this->state([
            'status' => GameStatus::Final,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'kickoff_at' => now()->subDay(),
        ]);
    }
}

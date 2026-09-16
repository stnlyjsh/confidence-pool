<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Pick;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pick>
 */
class PickFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pool_id' => Pool::factory(),
            'user_id' => User::factory(),
            // Passed as an unresolved factory (not ->create()) so overriding
            // 'game_id' in a create() call skips creating this row entirely,
            // rather than always creating a throwaway extra Game.
            'game_id' => Game::factory(),
            'picked_team_id' => null,
            'confidence_value' => $this->faker->numberBetween(1, 16),
            'season' => now()->year,
            'week' => 1,
            'is_auto_assigned' => false,
        ];
    }
}

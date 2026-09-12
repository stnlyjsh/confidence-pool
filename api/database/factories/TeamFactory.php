<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'espn_team_id' => (string) $this->faker->unique()->numberBetween(1, 34),
            'abbreviation' => strtoupper($this->faker->unique()->lexify('???')),
            'name' => $this->faker->city().' '.$this->faker->word(),
            'logo_url' => $this->faker->imageUrl(),
        ];
    }
}

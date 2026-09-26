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
        $city = $this->faker->city();

        return [
            'espn_team_id' => (string) $this->faker->unique()->numberBetween(1, 34),
            'abbreviation' => strtoupper($this->faker->unique()->lexify('???')),
            'name' => $city.' '.$this->faker->word(),
            'city' => $city,
            'logo_url' => $this->faker->imageUrl(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\PoolStatus;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pool>
 */
class PoolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true).' Confidence Pool',
            'season_year' => now()->year,
            'commissioner_user_id' => User::factory(),
            'buy_in_amount_cents' => 5000,
            'weekly_payout_cents' => 0,
            'season_payout_cents' => 0,
            'invite_code' => Str::upper(Str::random(8)),
            'status' => PoolStatus::Active,
        ];
    }
}

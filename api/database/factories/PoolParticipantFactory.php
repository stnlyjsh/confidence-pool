<?php

namespace Database\Factories;

use App\Enums\ParticipantRole;
use App\Models\Pool;
use App\Models\PoolParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PoolParticipant>
 */
class PoolParticipantFactory extends Factory
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
            'role' => ParticipantRole::Player,
            'is_active' => true,
            'joined_at' => now(),
        ];
    }

    public function commissioner(): static
    {
        return $this->state(['role' => ParticipantRole::Commissioner]);
    }
}

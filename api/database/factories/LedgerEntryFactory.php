<?php

namespace Database\Factories;

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
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
            'season' => now()->year,
            'type' => LedgerEntryType::BuyIn,
            'week' => null,
            'amount_cents' => 5000,
            'is_paid' => false,
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameHasLiveGamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_true_when_a_game_kicked_off_recently_and_is_not_final(): void
    {
        Game::factory()->create(['kickoff_at' => now()->subHours(2), 'status' => GameStatus::InProgress]);

        $this->assertTrue(Game::hasLiveGames());
    }

    public function test_false_when_no_game_has_kicked_off_yet(): void
    {
        Game::factory()->create(['kickoff_at' => now()->addHour(), 'status' => GameStatus::Scheduled]);

        $this->assertFalse(Game::hasLiveGames());
    }

    public function test_false_once_a_game_is_final(): void
    {
        Game::factory()->final()->create(['kickoff_at' => now()->subHours(2)]);

        $this->assertFalse(Game::hasLiveGames());
    }

    public function test_false_once_a_game_kicked_off_too_long_ago_to_still_be_playing(): void
    {
        Game::factory()->create(['kickoff_at' => now()->subHours(8), 'status' => GameStatus::InProgress]);

        $this->assertFalse(Game::hasLiveGames());
    }
}

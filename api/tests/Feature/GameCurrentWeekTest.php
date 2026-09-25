<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameCurrentWeekTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefers_a_week_with_a_game_still_being_played(): void
    {
        Game::factory()->final()->create(['season' => 2026, 'week' => 1]);
        Game::factory()->create(['season' => 2026, 'week' => 2, 'status' => GameStatus::InProgress, 'kickoff_at' => now()->subHour()]);
        Game::factory()->create(['season' => 2026, 'week' => 3, 'kickoff_at' => now()->addWeek()]);

        $this->assertSame(['season' => 2026, 'week' => 2], Game::currentWeek());
    }

    public function test_falls_back_to_the_soonest_upcoming_week_when_nothing_is_live(): void
    {
        Game::factory()->final()->create(['season' => 2026, 'week' => 1]);
        Game::factory()->create(['season' => 2026, 'week' => 3, 'kickoff_at' => now()->addWeeks(2)]);
        Game::factory()->create(['season' => 2026, 'week' => 2, 'kickoff_at' => now()->addWeek()]);

        $this->assertSame(['season' => 2026, 'week' => 2], Game::currentWeek());
    }

    public function test_falls_back_to_the_most_recently_played_week_once_the_season_is_over(): void
    {
        Game::factory()->final()->create(['season' => 2026, 'week' => 17, 'kickoff_at' => now()->subDays(10)]);
        Game::factory()->final()->create(['season' => 2026, 'week' => 18, 'kickoff_at' => now()->subDays(3)]);

        $this->assertSame(['season' => 2026, 'week' => 18], Game::currentWeek());
    }

    public function test_returns_null_when_nothing_has_synced_yet(): void
    {
        $this->assertNull(Game::currentWeek());
    }

    public function test_endpoint_returns_the_current_week(): void
    {
        $user = User::factory()->create();
        Game::factory()->create(['season' => 2026, 'week' => 5, 'status' => GameStatus::InProgress, 'kickoff_at' => now()->subMinutes(30)]);

        $response = $this->actingAs($user)->getJson('/api/weeks/current');

        $response->assertOk()->assertJson(['data' => ['season' => 2026, 'week' => 5]]);
    }

    public function test_endpoint_returns_404_when_nothing_has_synced_yet(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/weeks/current')->assertStatus(404);
    }

    public function test_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/weeks/current')->assertUnauthorized();
    }
}

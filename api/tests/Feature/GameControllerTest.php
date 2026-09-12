<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_games_for_a_season_and_week_ordered_by_kickoff(): void
    {
        $user = User::factory()->create();
        $later = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addDays(2)]);
        $earlier = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addDay()]);
        Game::factory()->create(['season' => 2026, 'week' => 2]);

        $response = $this->actingAs($user)->getJson('/api/weeks/2026/1/games');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertSame([$earlier->id, $later->id], $ids->all());
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/weeks/2026/1/games')->assertUnauthorized();
    }

    public function test_game_resource_includes_teams_and_lock_state(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->final()->create(['season' => 2026, 'week' => 1]);

        $response = $this->actingAs($user)->getJson('/api/weeks/2026/1/games');

        $response->assertOk()->assertJsonPath('data.0.is_locked', true);
        $this->assertSame($game->homeTeam->abbreviation, $response->json('data.0.home_team.abbreviation'));
    }
}

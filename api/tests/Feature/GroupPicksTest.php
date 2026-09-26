<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Pick;
use App\Models\Pool;
use App\Models\PoolParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupPicksTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/weeks/2026/1/group-picks')->assertUnauthorized();
    }

    public function test_games_are_ordered_by_kickoff_ascending(): void
    {
        $pool = Pool::factory()->create();
        $viewer = PoolParticipant::factory()->for($pool)->create();

        $later = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addDays(2)]);
        $earlier = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addDay()]);

        $response = $this->actingAs($viewer->user)->getJson('/api/weeks/2026/1/group-picks');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('game_id');
        $this->assertSame([$earlier->id, $later->id], $ids->all());
    }

    public function test_other_players_picks_are_hidden_for_an_unlocked_game(): void
    {
        $pool = Pool::factory()->create();
        $viewer = PoolParticipant::factory()->for($pool)->create();
        $other = PoolParticipant::factory()->for($pool)->create();
        $game = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addDay()]);

        Pick::factory()->create([
            'pool_id' => $pool->id, 'user_id' => $other->user_id, 'game_id' => $game->id,
            'picked_team_id' => $game->home_team_id, 'confidence_value' => 10, 'season' => 2026, 'week' => 1,
        ]);

        $response = $this->actingAs($viewer->user)->getJson('/api/weeks/2026/1/group-picks');

        $response->assertOk();
        $picks = collect($response->json('data.0.picks'))->keyBy('user_id');
        $this->assertFalse($picks[$other->user_id]['revealed']);
        $this->assertTrue($picks[$other->user_id]['has_picked']);
        $this->assertNull($picks[$other->user_id]['picked_team_id']);
        $this->assertNull($picks[$other->user_id]['confidence_value']);
    }

    public function test_you_can_always_see_your_own_pick_even_before_the_game_locks(): void
    {
        $pool = Pool::factory()->create();
        $viewer = PoolParticipant::factory()->for($pool)->create();
        $game = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addDay()]);

        Pick::factory()->create([
            'pool_id' => $pool->id, 'user_id' => $viewer->user_id, 'game_id' => $game->id,
            'picked_team_id' => $game->home_team_id, 'confidence_value' => 9, 'season' => 2026, 'week' => 1,
        ]);

        $response = $this->actingAs($viewer->user)->getJson('/api/weeks/2026/1/group-picks');

        $picks = collect($response->json('data.0.picks'))->keyBy('user_id');
        $this->assertTrue($picks[$viewer->user_id]['revealed']);
        $this->assertSame($game->home_team_id, $picks[$viewer->user_id]['picked_team_id']);
        $this->assertSame(9, $picks[$viewer->user_id]['confidence_value']);
    }

    public function test_everyone_is_revealed_once_the_game_is_locked(): void
    {
        $pool = Pool::factory()->create();
        $viewer = PoolParticipant::factory()->for($pool)->create();
        $other = PoolParticipant::factory()->for($pool)->create();
        $game = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->subHour()]);

        Pick::factory()->create([
            'pool_id' => $pool->id, 'user_id' => $other->user_id, 'game_id' => $game->id,
            'picked_team_id' => $game->away_team_id, 'confidence_value' => 5, 'season' => 2026, 'week' => 1,
        ]);

        $response = $this->actingAs($viewer->user)->getJson('/api/weeks/2026/1/group-picks');

        $picks = collect($response->json('data.0.picks'))->keyBy('user_id');
        $this->assertTrue($picks[$other->user_id]['revealed']);
        $this->assertSame($game->away_team_id, $picks[$other->user_id]['picked_team_id']);
        $this->assertSame(5, $picks[$other->user_id]['confidence_value']);
    }

    public function test_a_missed_pick_on_a_locked_game_shows_as_not_picked_once_auto_filled(): void
    {
        $pool = Pool::factory()->create();
        $viewer = PoolParticipant::factory()->for($pool)->create();
        $ghost = PoolParticipant::factory()->for($pool)->create();
        Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->subMinute()]);

        // No pick created for $ghost — lockDueGames() (called inside the
        // endpoint) should auto-fill it before the response is built.
        $response = $this->actingAs($viewer->user)->getJson('/api/weeks/2026/1/group-picks');

        $picks = collect($response->json('data.0.picks'))->keyBy('user_id');
        $this->assertFalse($picks[$ghost->user_id]['has_picked']);
        $this->assertTrue($picks[$ghost->user_id]['revealed']);
        $this->assertNull($picks[$ghost->user_id]['picked_team_id']);
        $this->assertTrue($picks[$ghost->user_id]['is_auto_assigned']);
    }

    public function test_inactive_participants_are_excluded(): void
    {
        $pool = Pool::factory()->create();
        $viewer = PoolParticipant::factory()->for($pool)->create();
        $inactive = PoolParticipant::factory()->for($pool)->create(['is_active' => false]);
        Game::factory()->create(['season' => 2026, 'week' => 1]);

        $response = $this->actingAs($viewer->user)->getJson('/api/weeks/2026/1/group-picks');

        $userIds = collect($response->json('data.0.picks'))->pluck('user_id');
        $this->assertFalse($userIds->contains($inactive->user_id));
    }
}

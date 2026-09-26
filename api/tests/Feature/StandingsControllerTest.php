<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Pick;
use App\Models\Pool;
use App\Models\PoolParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_season_standings_sum_points_across_all_weeks(): void
    {
        $pool = Pool::factory()->create(['season_year' => 2026]);
        $alice = PoolParticipant::factory()->for($pool)->create();
        $bob = PoolParticipant::factory()->for($pool)->create();

        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $alice->user_id, 'season' => 2026, 'week' => 1, 'points_earned' => 10, 'is_correct' => true]);
        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $alice->user_id, 'season' => 2026, 'week' => 2, 'points_earned' => 5, 'is_correct' => true]);
        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $bob->user_id, 'season' => 2026, 'week' => 1, 'points_earned' => 8, 'is_correct' => true]);

        $response = $this->actingAs($alice->user)->getJson('/api/standings/season');

        $response->assertOk();
        $data = collect($response->json('data'))->keyBy('user_id');
        $this->assertSame(15, $data[$alice->user_id]['total_points']);
        $this->assertSame(8, $data[$bob->user_id]['total_points']);
    }

    public function test_weekly_standings_only_counts_that_week(): void
    {
        $pool = Pool::factory()->create(['season_year' => 2026]);
        $alice = PoolParticipant::factory()->for($pool)->create();

        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $alice->user_id, 'season' => 2026, 'week' => 1, 'points_earned' => 10, 'is_correct' => true]);
        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $alice->user_id, 'season' => 2026, 'week' => 2, 'points_earned' => 5, 'is_correct' => true]);

        $response = $this->actingAs($alice->user)->getJson('/api/standings/weeks/1');

        $response->assertOk();
        $data = collect($response->json('data'))->keyBy('user_id');
        $this->assertSame(10, $data[$alice->user_id]['total_points']);
    }

    public function test_a_participant_with_no_picks_yet_shows_zero(): void
    {
        $pool = Pool::factory()->create(['season_year' => 2026]);
        $freshJoiner = PoolParticipant::factory()->for($pool)->create();

        $response = $this->actingAs($freshJoiner->user)->getJson('/api/standings/season');

        $response->assertOk();
        $data = collect($response->json('data'))->keyBy('user_id');
        $this->assertSame(0, $data[$freshJoiner->user_id]['total_points']);
    }

    public function test_an_unmade_or_unscored_pick_does_not_break_the_sum(): void
    {
        $pool = Pool::factory()->create(['season_year' => 2026]);
        $alice = PoolParticipant::factory()->for($pool)->create();

        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $alice->user_id, 'season' => 2026, 'week' => 1, 'points_earned' => 10, 'is_correct' => true]);
        // A game that has not finished yet — not scored, points_earned null.
        Game::factory()->create();
        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $alice->user_id, 'season' => 2026, 'week' => 2, 'points_earned' => null, 'is_correct' => null]);

        $response = $this->actingAs($alice->user)->getJson('/api/standings/season');

        $response->assertOk();
        $data = collect($response->json('data'))->keyBy('user_id');
        $this->assertSame(10, $data[$alice->user_id]['total_points']);
    }

    public function test_pct_correct_is_null_until_any_pick_is_decided(): void
    {
        $pool = Pool::factory()->create(['season_year' => 2026]);
        $alice = PoolParticipant::factory()->for($pool)->create();

        $game = Game::factory()->create();
        Pick::factory()->create([
            'pool_id' => $pool->id, 'user_id' => $alice->user_id, 'game_id' => $game->id,
            'season' => 2026, 'week' => 1, 'picked_team_id' => $game->home_team_id,
            'points_earned' => null, 'is_correct' => null,
        ]);

        $response = $this->actingAs($alice->user)->getJson('/api/standings/season');

        $data = collect($response->json('data'))->keyBy('user_id');
        $this->assertNull($data[$alice->user_id]['pct_correct']);
    }

    public function test_pct_correct_only_counts_decided_picks(): void
    {
        $pool = Pool::factory()->create(['season_year' => 2026]);
        $alice = PoolParticipant::factory()->for($pool)->create();

        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $alice->user_id, 'season' => 2026, 'week' => 1, 'points_earned' => 10, 'is_correct' => true]);
        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $alice->user_id, 'season' => 2026, 'week' => 2, 'points_earned' => 0, 'is_correct' => false]);
        // Still undecided — should not count toward the denominator.
        $game = Game::factory()->create();
        Pick::factory()->create([
            'pool_id' => $pool->id, 'user_id' => $alice->user_id, 'game_id' => $game->id,
            'season' => 2026, 'week' => 3, 'picked_team_id' => $game->home_team_id,
            'points_earned' => null, 'is_correct' => null,
        ]);

        $response = $this->actingAs($alice->user)->getJson('/api/standings/season');

        $data = collect($response->json('data'))->keyBy('user_id');
        $this->assertEquals(50.0, $data[$alice->user_id]['pct_correct']);
    }

    public function test_max_potential_points_includes_points_still_reachable(): void
    {
        $pool = Pool::factory()->create(['season_year' => 2026]);
        $alice = PoolParticipant::factory()->for($pool)->create();

        // Already decided: locked in at 10.
        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $alice->user_id, 'season' => 2026, 'week' => 1, 'points_earned' => 10, 'is_correct' => true]);
        // Still in play with a team picked: could still land its confidence value.
        $inPlay = Game::factory()->create();
        Pick::factory()->create([
            'pool_id' => $pool->id, 'user_id' => $alice->user_id, 'game_id' => $inPlay->id,
            'season' => 2026, 'week' => 2, 'picked_team_id' => $inPlay->home_team_id,
            'confidence_value' => 7, 'points_earned' => null, 'is_correct' => null,
        ]);
        // Locked with no team ever chosen (auto-fill placeholder pre-scoring): can only ever score 0.
        $noPick = Game::factory()->create();
        Pick::factory()->create([
            'pool_id' => $pool->id, 'user_id' => $alice->user_id, 'game_id' => $noPick->id,
            'season' => 2026, 'week' => 3, 'picked_team_id' => null,
            'confidence_value' => 16, 'points_earned' => null, 'is_correct' => null, 'is_auto_assigned' => true,
        ]);
        // Voided: excluded even though a team was picked.
        $voided = Game::factory()->create(['status' => GameStatus::Voided]);
        Pick::factory()->create([
            'pool_id' => $pool->id, 'user_id' => $alice->user_id, 'game_id' => $voided->id,
            'season' => 2026, 'week' => 4, 'picked_team_id' => $voided->home_team_id,
            'confidence_value' => 12, 'points_earned' => null, 'is_correct' => null,
        ]);

        $response = $this->actingAs($alice->user)->getJson('/api/standings/season');

        $data = collect($response->json('data'))->keyBy('user_id');
        $this->assertSame(17, $data[$alice->user_id]['max_potential_points']);
    }
}

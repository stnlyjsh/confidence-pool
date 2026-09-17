<?php

namespace Tests\Feature;

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
}

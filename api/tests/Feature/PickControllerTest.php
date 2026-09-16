<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Pick;
use App\Models\Pool;
use App\Models\PoolParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickControllerTest extends TestCase
{
    use RefreshDatabase;

    private function participant(Pool $pool): PoolParticipant
    {
        return PoolParticipant::factory()->for($pool)->create();
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/weeks/2026/1/picks')->assertUnauthorized();
        $this->putJson('/api/weeks/2026/1/picks', ['picks' => []])->assertUnauthorized();
    }

    public function test_it_saves_a_ranked_set_of_picks_and_computes_descending_values(): void
    {
        $pool = Pool::factory()->create();
        $participant = $this->participant($pool);
        $gameA = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addDay()]);
        $gameB = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addDays(2)]);

        $response = $this->actingAs($participant->user)->putJson('/api/weeks/2026/1/picks', [
            'picks' => [
                ['game_id' => $gameA->id, 'picked_team_id' => $gameA->home_team_id],
                ['game_id' => $gameB->id, 'picked_team_id' => $gameB->away_team_id],
            ],
        ]);

        $response->assertOk();
        $byGame = collect($response->json('data'))->keyBy('game_id');
        // Most confident (first in the ranked list) gets the highest value.
        $this->assertSame(2, $byGame[$gameA->id]['confidence_value']);
        $this->assertSame(1, $byGame[$gameB->id]['confidence_value']);
        $this->assertSame($gameA->home_team_id, $byGame[$gameA->id]['picked_team_id']);
    }

    public function test_reordering_reflows_values_across_unlocked_picks(): void
    {
        $pool = Pool::factory()->create();
        $participant = $this->participant($pool);
        $gameA = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addDay()]);
        $gameB = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addDays(2)]);

        $this->actingAs($participant->user)->putJson('/api/weeks/2026/1/picks', [
            'picks' => [
                ['game_id' => $gameA->id, 'picked_team_id' => $gameA->home_team_id],
                ['game_id' => $gameB->id, 'picked_team_id' => $gameB->away_team_id],
            ],
        ]);

        // Swap the order.
        $response = $this->actingAs($participant->user)->putJson('/api/weeks/2026/1/picks', [
            'picks' => [
                ['game_id' => $gameB->id, 'picked_team_id' => $gameB->away_team_id],
                ['game_id' => $gameA->id, 'picked_team_id' => $gameA->home_team_id],
            ],
        ]);

        $byGame = collect($response->json('data'))->keyBy('game_id');
        $this->assertSame(2, $byGame[$gameB->id]['confidence_value']);
        $this->assertSame(1, $byGame[$gameA->id]['confidence_value']);
    }

    public function test_it_reserves_values_already_used_by_locked_picks(): void
    {
        $pool = Pool::factory()->create();
        $participant = $this->participant($pool);
        $lockedGame = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->subHour()]);
        $openGame = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addDay()]);

        Pick::factory()->create([
            'pool_id' => $pool->id, 'user_id' => $participant->user_id, 'game_id' => $lockedGame->id,
            'confidence_value' => 2, 'season' => 2026, 'week' => 1,
        ]);

        $response = $this->actingAs($participant->user)->putJson('/api/weeks/2026/1/picks', [
            'picks' => [
                ['game_id' => $openGame->id, 'picked_team_id' => $openGame->home_team_id],
            ],
        ]);

        $response->assertOk();
        $byGame = collect($response->json('data'))->keyBy('game_id');
        // Only value 1 is left since 2 is reserved by the locked pick.
        $this->assertSame(1, $byGame[$openGame->id]['confidence_value']);
    }

    public function test_it_rejects_a_pick_for_a_game_that_has_already_kicked_off(): void
    {
        $pool = Pool::factory()->create();
        $participant = $this->participant($pool);
        $lockedGame = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->subHour()]);

        $response = $this->actingAs($participant->user)->putJson('/api/weeks/2026/1/picks', [
            'picks' => [
                ['game_id' => $lockedGame->id, 'picked_team_id' => $lockedGame->home_team_id],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_it_rejects_a_team_that_is_not_playing_in_the_game(): void
    {
        $pool = Pool::factory()->create();
        $participant = $this->participant($pool);
        $game = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addDay()]);
        $otherGame = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addDay()]);

        $response = $this->actingAs($participant->user)->putJson('/api/weeks/2026/1/picks', [
            'picks' => [
                ['game_id' => $game->id, 'picked_team_id' => $otherGame->home_team_id],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_it_rejects_duplicate_games_in_the_same_submission(): void
    {
        $pool = Pool::factory()->create();
        $participant = $this->participant($pool);
        $game = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addDay()]);

        $response = $this->actingAs($participant->user)->putJson('/api/weeks/2026/1/picks', [
            'picks' => [
                ['game_id' => $game->id, 'picked_team_id' => $game->home_team_id],
                ['game_id' => $game->id, 'picked_team_id' => $game->away_team_id],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_index_auto_fills_a_newly_locked_game_before_returning_picks(): void
    {
        $pool = Pool::factory()->create();
        $participant = $this->participant($pool);
        Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->subMinute()]);

        $response = $this->actingAs($participant->user)->getJson('/api/weeks/2026/1/picks');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.is_auto_assigned'));
    }

    public function test_a_partial_pick_set_is_allowed_leaving_other_games_unpicked(): void
    {
        $pool = Pool::factory()->create();
        $participant = $this->participant($pool);
        $picked = Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addDay()]);
        Game::factory()->create(['season' => 2026, 'week' => 1, 'kickoff_at' => now()->addDays(2)]);

        $response = $this->actingAs($participant->user)->putJson('/api/weeks/2026/1/picks', [
            'picks' => [
                ['game_id' => $picked->id, 'picked_team_id' => $picked->home_team_id],
            ],
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}

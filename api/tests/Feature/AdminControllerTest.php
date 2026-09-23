<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Events\GameScoreUpdated;
use App\Events\StandingsUpdated;
use App\Models\Game;
use App\Models\Pick;
use App\Models\Pool;
use App\Models\PoolParticipant;
use App\Services\ConfidencePoolScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_commissioner_can_void_a_game(): void
    {
        $pool = Pool::factory()->create();
        $commissioner = PoolParticipant::factory()->commissioner()->for($pool)->create()->user;
        $game = Game::factory()->final()->create();
        $pick = Pick::factory()->create(['game_id' => $game->id, 'picked_team_id' => $game->home_team_id, 'confidence_value' => 10]);

        $response = $this->actingAs($commissioner)->postJson("/api/admin/games/{$game->id}/void");

        $response->assertOk()->assertJsonPath('data.status', 'voided');
        $this->assertSame(GameStatus::Voided, $game->fresh()->status);
        $pick->refresh();
        $this->assertNull($pick->is_correct);
        $this->assertNull($pick->points_earned);
    }

    public function test_voiding_a_game_broadcasts_score_and_standings_updates(): void
    {
        $pool = Pool::factory()->create();
        $commissioner = PoolParticipant::factory()->commissioner()->for($pool)->create()->user;
        $game = Game::factory()->final()->create(['week' => 3]);
        $pick = Pick::factory()->create(['game_id' => $game->id, 'picked_team_id' => $game->home_team_id, 'confidence_value' => 10]);
        app(ConfidencePoolScorer::class)->scoreGame($game);
        $this->assertTrue($pick->fresh()->is_correct); // sanity check: something to actually undo

        Event::fake([GameScoreUpdated::class, StandingsUpdated::class]);

        $this->actingAs($commissioner)->postJson("/api/admin/games/{$game->id}/void")->assertOk();

        Event::assertDispatched(GameScoreUpdated::class, fn (GameScoreUpdated $e) => $e->poolId === $pool->id && $e->gameId === $game->id && $e->status === 'voided');
        Event::assertDispatched(StandingsUpdated::class, fn (StandingsUpdated $e) => $e->poolId === $pool->id && $e->week === 3);
    }

    public function test_player_cannot_void_a_game(): void
    {
        $pool = Pool::factory()->create();
        $player = PoolParticipant::factory()->for($pool)->create()->user;
        $game = Game::factory()->create();

        $this->actingAs($player)->postJson("/api/admin/games/{$game->id}/void")->assertForbidden();
    }

    public function test_close_week_is_blocked_until_every_game_is_final(): void
    {
        $pool = Pool::factory()->create(['season_year' => 2026]);
        $commissioner = PoolParticipant::factory()->commissioner()->for($pool)->create()->user;
        Game::factory()->create(['season' => 2026, 'week' => 1, 'status' => GameStatus::Scheduled]);

        $response = $this->actingAs($commissioner)->postJson('/api/admin/close-week/1');

        $response->assertStatus(422);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_close_week_succeeds_once_every_game_is_final_or_voided(): void
    {
        $pool = Pool::factory()->create(['season_year' => 2026, 'weekly_payout_cents' => 10000]);
        $winner = PoolParticipant::factory()->for($pool)->create();
        $commissioner = PoolParticipant::factory()->commissioner()->for($pool)->create()->user;
        $finalGame = Game::factory()->final()->create(['season' => 2026, 'week' => 1]);
        Game::factory()->create(['season' => 2026, 'week' => 1, 'status' => GameStatus::Voided]);
        Pick::factory()->create(['pool_id' => $pool->id, 'user_id' => $winner->user_id, 'game_id' => $finalGame->id, 'season' => 2026, 'week' => 1, 'points_earned' => 10]);

        $response = $this->actingAs($commissioner)->postJson('/api/admin/close-week/1');

        $response->assertOk();
        $this->assertDatabaseHas('ledger_entries', ['user_id' => $winner->user_id, 'type' => 'weekly_payout']);
    }

    public function test_player_cannot_close_the_week(): void
    {
        $pool = Pool::factory()->create(['season_year' => 2026]);
        $player = PoolParticipant::factory()->for($pool)->create()->user;

        $this->actingAs($player)->postJson('/api/admin/close-week/1')->assertForbidden();
    }

    public function test_commissioner_can_manually_finalize_a_game(): void
    {
        $pool = Pool::factory()->create();
        $commissioner = PoolParticipant::factory()->commissioner()->for($pool)->create()->user;
        $game = Game::factory()->create(['status' => GameStatus::Scheduled]);
        $pick = Pick::factory()->create(['game_id' => $game->id, 'picked_team_id' => $game->home_team_id, 'confidence_value' => 7]);

        $response = $this->actingAs($commissioner)->patchJson("/api/admin/games/{$game->id}", [
            'status' => 'final',
            'home_score' => 24,
            'away_score' => 17,
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'final');
        $game->refresh();
        $this->assertSame(24, $game->home_score);
        $this->assertSame(17, $game->away_score);
        $pick->refresh();
        $this->assertTrue($pick->is_correct);
        $this->assertSame(7, $pick->points_earned);
    }

    public function test_manually_marking_a_game_postponed_clears_any_scores(): void
    {
        $pool = Pool::factory()->create();
        $commissioner = PoolParticipant::factory()->commissioner()->for($pool)->create()->user;
        $game = Game::factory()->final()->create();

        $response = $this->actingAs($commissioner)->patchJson("/api/admin/games/{$game->id}", [
            'status' => 'postponed',
        ]);

        $response->assertOk();
        $game->refresh();
        $this->assertSame(GameStatus::Postponed, $game->status);
        $this->assertNull($game->home_score);
        $this->assertNull($game->away_score);
    }

    public function test_override_requires_scores_when_finalizing(): void
    {
        $pool = Pool::factory()->create();
        $commissioner = PoolParticipant::factory()->commissioner()->for($pool)->create()->user;
        $game = Game::factory()->create();

        $response = $this->actingAs($commissioner)->patchJson("/api/admin/games/{$game->id}", [
            'status' => 'final',
        ]);

        $response->assertStatus(422);
    }

    public function test_override_rejects_voided_as_a_status(): void
    {
        $pool = Pool::factory()->create();
        $commissioner = PoolParticipant::factory()->commissioner()->for($pool)->create()->user;
        $game = Game::factory()->create();

        $response = $this->actingAs($commissioner)->patchJson("/api/admin/games/{$game->id}", [
            'status' => 'voided',
        ]);

        $response->assertStatus(422);
    }

    public function test_player_cannot_override_a_game(): void
    {
        $pool = Pool::factory()->create();
        $player = PoolParticipant::factory()->for($pool)->create()->user;
        $game = Game::factory()->create();

        $this->actingAs($player)->patchJson("/api/admin/games/{$game->id}", [
            'status' => 'final',
            'home_score' => 10,
            'away_score' => 7,
        ])->assertForbidden();
    }

    public function test_voiding_a_game_succeeds_even_if_broadcasting_is_unreachable(): void
    {
        $pool = Pool::factory()->create();
        $commissioner = PoolParticipant::factory()->commissioner()->for($pool)->create()->user;
        $game = Game::factory()->final()->create();

        // Real dispatch (not Event::fake()) so this exercises the actual
        // try/catch in GameUpdateBroadcaster — a listener throwing stands
        // in for Reverb being unreachable, which is exactly what surfaced
        // this bug: a broadcast failure was turning a successful void into
        // a 500 response.
        Event::listen(GameScoreUpdated::class, fn () => throw new \RuntimeException('Reverb is down'));

        $response = $this->actingAs($commissioner)->postJson("/api/admin/games/{$game->id}/void");

        $response->assertOk();
        $this->assertSame(GameStatus::Voided, $game->fresh()->status);
    }
}

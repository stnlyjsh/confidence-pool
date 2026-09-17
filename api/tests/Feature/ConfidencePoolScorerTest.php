<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Pick;
use App\Services\ConfidencePoolScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfidencePoolScorerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_correct_pick_earns_its_confidence_value(): void
    {
        $game = Game::factory()->final(home: 24, away: 17)->create();
        $pick = Pick::factory()->create([
            'game_id' => $game->id,
            'picked_team_id' => $game->home_team_id,
            'confidence_value' => 12,
        ]);

        app(ConfidencePoolScorer::class)->scoreGame($game);

        $pick->refresh();
        $this->assertTrue($pick->is_correct);
        $this->assertSame(12, $pick->points_earned);
    }

    public function test_an_incorrect_pick_earns_zero(): void
    {
        $game = Game::factory()->final(home: 24, away: 17)->create();
        $pick = Pick::factory()->create([
            'game_id' => $game->id,
            'picked_team_id' => $game->away_team_id,
            'confidence_value' => 12,
        ]);

        app(ConfidencePoolScorer::class)->scoreGame($game);

        $pick->refresh();
        $this->assertFalse($pick->is_correct);
        $this->assertSame(0, $pick->points_earned);
    }

    public function test_an_unmade_pick_earns_zero(): void
    {
        $game = Game::factory()->final(home: 24, away: 17)->create();
        $pick = Pick::factory()->create([
            'game_id' => $game->id,
            'picked_team_id' => null,
            'confidence_value' => 16,
            'is_auto_assigned' => true,
        ]);

        app(ConfidencePoolScorer::class)->scoreGame($game);

        $pick->refresh();
        $this->assertFalse($pick->is_correct);
        $this->assertSame(0, $pick->points_earned);
    }

    public function test_a_tied_game_scores_zero_for_everyone(): void
    {
        $game = Game::factory()->final(home: 20, away: 20)->create();
        $homePick = Pick::factory()->create(['game_id' => $game->id, 'picked_team_id' => $game->home_team_id, 'confidence_value' => 10]);
        $awayPick = Pick::factory()->create(['game_id' => $game->id, 'picked_team_id' => $game->away_team_id, 'confidence_value' => 8]);

        app(ConfidencePoolScorer::class)->scoreGame($game);

        $homePick->refresh();
        $awayPick->refresh();
        $this->assertFalse($homePick->is_correct);
        $this->assertFalse($awayPick->is_correct);
        $this->assertSame(0, $homePick->points_earned);
        $this->assertSame(0, $awayPick->points_earned);
    }

    public function test_a_voided_game_excludes_its_picks_from_scoring_entirely(): void
    {
        $game = Game::factory()->final(home: 24, away: 17)->create();
        $pick = Pick::factory()->create([
            'game_id' => $game->id,
            'picked_team_id' => $game->home_team_id,
            'confidence_value' => 12,
        ]);

        app(ConfidencePoolScorer::class)->scoreGame($game);
        $pick->refresh();
        $this->assertTrue($pick->is_correct);

        $game->update(['status' => GameStatus::Voided]);
        app(ConfidencePoolScorer::class)->scoreGame($game);

        $pick->refresh();
        $this->assertNull($pick->is_correct);
        $this->assertNull($pick->points_earned);
    }

    public function test_a_game_that_has_not_finished_is_not_scored(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::InProgress]);
        $pick = Pick::factory()->create(['game_id' => $game->id, 'picked_team_id' => $game->home_team_id]);

        app(ConfidencePoolScorer::class)->scoreGame($game);

        $pick->refresh();
        $this->assertNull($pick->is_correct);
        $this->assertNull($pick->points_earned);
    }

    public function test_scoring_is_idempotent_and_handles_a_later_score_correction(): void
    {
        $game = Game::factory()->final(home: 24, away: 17)->create();
        $pick = Pick::factory()->create(['game_id' => $game->id, 'picked_team_id' => $game->home_team_id, 'confidence_value' => 12]);

        $scorer = app(ConfidencePoolScorer::class);
        $scorer->scoreGame($game);
        $scorer->scoreGame($game);

        $pick->refresh();
        $this->assertTrue($pick->is_correct);
        $this->assertSame(12, $pick->points_earned);

        // ESPN corrects the final score after the fact.
        $game->update(['home_score' => 17, 'away_score' => 24]);
        $scorer->scoreGame($game);

        $pick->refresh();
        $this->assertFalse($pick->is_correct);
        $this->assertSame(0, $pick->points_earned);
    }
}

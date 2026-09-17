<?php

namespace App\Services;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Pick;

class ConfidencePoolScorer
{
    /**
     * Score (or re-score) every pick for a game. Idempotent, so it's safe
     * to call on every sync — including one that corrects an already-final
     * score, or a game the commissioner just voided.
     */
    public function scoreGame(Game $game): int
    {
        if ($game->status === GameStatus::Voided) {
            return $this->voidPicksForGame($game);
        }

        if ($game->status !== GameStatus::Final) {
            return 0;
        }

        // A tie means no one picked correctly — the confidence points on
        // that pick are simply lost for every participant.
        $winnerTeamId = match (true) {
            $game->home_score > $game->away_score => $game->home_team_id,
            $game->away_score > $game->home_score => $game->away_team_id,
            default => null,
        };

        $updated = 0;

        foreach (Pick::where('game_id', $game->id)->get() as $pick) {
            $isCorrect = $winnerTeamId !== null && $pick->picked_team_id === $winnerTeamId;
            $pointsEarned = $isCorrect ? $pick->confidence_value : 0;

            if ($pick->is_correct !== $isCorrect || $pick->points_earned !== $pointsEarned) {
                $pick->update(['is_correct' => $isCorrect, 'points_earned' => $pointsEarned]);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * A voided game (postponed/canceled and resolved by the commissioner)
     * is excluded from scoring entirely, as if it never happened.
     */
    private function voidPicksForGame(Game $game): int
    {
        return Pick::where('game_id', $game->id)
            ->where(fn ($query) => $query->whereNotNull('is_correct')->orWhereNotNull('points_earned'))
            ->update(['is_correct' => null, 'points_earned' => null]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Http\Resources\GameResource;
use App\Models\Game;
use App\Services\ConfidencePoolScorer;

class AdminController extends Controller
{
    /**
     * Void a game (a postponed/canceled game the commissioner has
     * resolved) — it's excluded from scoring entirely for every
     * participant, as if it never happened.
     */
    public function voidGame(Game $game, ConfidencePoolScorer $scorer): GameResource
    {
        $game->update(['status' => GameStatus::Voided]);
        $scorer->scoreGame($game);

        return new GameResource($game->load(['homeTeam', 'awayTeam']));
    }
}

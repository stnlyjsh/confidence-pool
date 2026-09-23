<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Events\GameScoreUpdated;
use App\Events\StandingsUpdated;
use App\Http\Resources\GameResource;
use App\Http\Resources\LedgerEntryResource;
use App\Models\Game;
use App\Models\Pool;
use App\Services\ConfidencePoolScorer;
use App\Services\LedgerService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
        $updatedPicks = $scorer->scoreGame($game);

        if ($poolId = Pool::query()->value('id')) {
            GameScoreUpdated::dispatch($poolId, $game->id, $game->home_score, $game->away_score, $game->status->value);

            if ($updatedPicks > 0) {
                StandingsUpdated::dispatch($poolId, $game->week);
            }
        }

        return new GameResource($game->load(['homeTeam', 'awayTeam']));
    }

    /**
     * Create the weekly payout entries for that week's top scorer(s), once
     * every game that week has a final result (or has been voided).
     */
    public function closeWeek(int $week, LedgerService $ledger): AnonymousResourceCollection
    {
        $pool = Pool::sole();

        $unfinished = Game::where('season', $pool->season_year)
            ->where('week', $week)
            ->whereNotIn('status', [GameStatus::Final, GameStatus::Voided])
            ->exists();

        abort_if($unfinished, 422, 'Not every game this week is final yet.');

        return LedgerEntryResource::collection($ledger->closeWeek($pool, $week));
    }

    /**
     * Same idea as closeWeek, but for the season champion(s).
     */
    public function closeSeason(LedgerService $ledger): AnonymousResourceCollection
    {
        $pool = Pool::sole();

        $unfinished = Game::where('season', $pool->season_year)
            ->whereNotIn('status', [GameStatus::Final, GameStatus::Voided])
            ->exists();

        abort_if($unfinished, 422, 'Not every game this season is final yet.');

        return LedgerEntryResource::collection($ledger->closeSeason($pool));
    }
}

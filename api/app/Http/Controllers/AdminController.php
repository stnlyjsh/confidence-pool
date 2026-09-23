<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Http\Requests\OverrideGameRequest;
use App\Http\Resources\GameResource;
use App\Http\Resources\LedgerEntryResource;
use App\Models\Game;
use App\Models\Pool;
use App\Services\ConfidencePoolScorer;
use App\Services\GameUpdateBroadcaster;
use App\Services\LedgerService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminController extends Controller
{
    /**
     * Void a game (a postponed/canceled game the commissioner has
     * resolved) — it's excluded from scoring entirely for every
     * participant, as if it never happened.
     */
    public function voidGame(Game $game, ConfidencePoolScorer $scorer, GameUpdateBroadcaster $broadcaster): GameResource
    {
        $game->update(['status' => GameStatus::Voided]);

        return $this->rescoreAndRespond($game, $scorer, $broadcaster);
    }

    /**
     * Manually set a game's result — for when ESPN's unofficial endpoint is
     * down, wrong, or just slow, so a week doesn't get stuck waiting on it.
     * Not sticky like a void: a later real ESPN sync is free to correct it.
     */
    public function overrideGame(
        OverrideGameRequest $request,
        Game $game,
        ConfidencePoolScorer $scorer,
        GameUpdateBroadcaster $broadcaster,
    ): GameResource {
        $status = GameStatus::from($request->validated('status'));

        $game->update([
            'status' => $status,
            'home_score' => $status === GameStatus::Final ? $request->validated('home_score') : null,
            'away_score' => $status === GameStatus::Final ? $request->validated('away_score') : null,
        ]);

        return $this->rescoreAndRespond($game, $scorer, $broadcaster);
    }

    private function rescoreAndRespond(Game $game, ConfidencePoolScorer $scorer, GameUpdateBroadcaster $broadcaster): GameResource
    {
        $updatedPicks = $scorer->scoreGame($game);
        $broadcaster->broadcast($game, scoreChanged: true, updatedPicks: $updatedPicks);

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

<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePicksRequest;
use App\Http\Resources\PickResource;
use App\Http\Resources\TeamResource;
use App\Models\Game;
use App\Models\Pick;
use App\Models\Pool;
use App\Models\PoolParticipant;
use App\Services\PickLockService;
use App\Services\PickValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

class PickController extends Controller
{
    public function __construct(
        private readonly PickLockService $lockService,
        private readonly PickValidationService $validationService,
    ) {}

    public function index(Request $request, int $season, int $week): AnonymousResourceCollection
    {
        // Catch up any game that kicked off since this user last loaded the
        // page, so what they see already reflects any auto-filled picks.
        $this->lockService->lockDueGames($season, $week);

        $picks = Pick::where('pool_id', Pool::sole()->id)
            ->where('user_id', $request->user()->id)
            ->where('season', $season)
            ->where('week', $week)
            ->with('game')
            ->get();

        return PickResource::collection($picks);
    }

    public function update(UpdatePicksRequest $request, int $season, int $week): AnonymousResourceCollection
    {
        $picks = $this->validationService->replaceUnlockedPicks(
            $request->user(),
            Pool::sole(),
            $season,
            $week,
            $request->validated('picks'),
        );

        return PickResource::collection($picks);
    }

    /**
     * Everyone's picks for the week, one row per game ordered by kickoff.
     * A player's specific pick and confidence value are only revealed once
     * that game locks — before then, only whether they've picked at all is
     * shown, so no one can copy another player's strategy before their own
     * deadline. You can always see your own pick, locked or not.
     */
    public function group(Request $request, int $season, int $week): JsonResponse
    {
        $this->lockService->lockDueGames($season, $week);

        $pool = Pool::sole();
        $currentUserId = $request->user()->id;

        $games = Game::with(['homeTeam', 'awayTeam'])
            ->where('season', $season)
            ->where('week', $week)
            ->orderBy('kickoff_at')
            ->get();

        $participants = $pool->participants()->where('is_active', true)->with('user')->get();

        $picksByGame = Pick::where('pool_id', $pool->id)
            ->where('season', $season)
            ->where('week', $week)
            ->get()
            ->groupBy('game_id');

        $data = $games->map(function (Game $game) use ($participants, $picksByGame, $currentUserId) {
            $isLocked = $game->kickoff_at->isPast();
            /** @var Collection<int, Pick> $picksForGame */
            $picksForGame = $picksByGame->get($game->id, collect())->keyBy('user_id');

            $picks = $participants->map(function (PoolParticipant $participant) use ($picksForGame, $isLocked, $currentUserId) {
                $pick = $picksForGame->get($participant->user_id);
                $revealed = $isLocked || $participant->user_id === $currentUserId;

                return [
                    'user_id' => $participant->user_id,
                    'name' => $participant->user->name,
                    'has_picked' => $pick !== null && $pick->picked_team_id !== null,
                    'revealed' => $revealed,
                    'picked_team_id' => $revealed ? $pick?->picked_team_id : null,
                    'confidence_value' => $revealed ? $pick?->confidence_value : null,
                    'is_auto_assigned' => $revealed ? (bool) $pick?->is_auto_assigned : null,
                ];
            });

            return [
                'game_id' => $game->id,
                'kickoff_at' => $game->kickoff_at,
                'is_locked' => $isLocked,
                'home_team' => new TeamResource($game->homeTeam),
                'away_team' => new TeamResource($game->awayTeam),
                'picks' => $picks,
            ];
        });

        return response()->json(['data' => $data]);
    }
}

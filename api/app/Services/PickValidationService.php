<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Pick;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PickValidationService
{
    public function __construct(private readonly PickLockService $lockService) {}

    /**
     * Replace a user's picks for every currently-unlocked game in the given
     * week with the submitted, ranked set. $rankedItems is ordered most to
     * least confident; confidence values are computed here — never trusted
     * from the client — as whatever's left in 1..N once games already
     * locked (manually picked-then-locked, or auto-filled) have claimed
     * their share. That's what lets the drag-reorder UI "just work": the
     * remaining values reflow across the still-open games in rank order
     * every time this runs.
     *
     * @param  array<int, array{game_id: int, picked_team_id: int}>  $rankedItems
     * @return Collection<int, Pick>
     */
    public function replaceUnlockedPicks(User $user, Pool $pool, int $season, int $week, array $rankedItems): Collection
    {
        return DB::transaction(function () use ($user, $pool, $season, $week, $rankedItems) {
            // Catch up any game that kicked off since the page was last loaded,
            // so its value is reserved before we compute what's left.
            $this->lockService->lockDueGames($season, $week);

            $games = Game::where('season', $season)->where('week', $week)
                ->with(['homeTeam', 'awayTeam'])
                ->get()
                ->keyBy('id');
            $totalGames = $games->count();

            abort_if($totalGames === 0, 404, 'No games found for that week.');

            $gameIds = collect($rankedItems)->pluck('game_id');
            abort_if($gameIds->duplicates()->isNotEmpty(), 422, 'Each game can only appear once.');

            foreach ($rankedItems as $item) {
                $game = $games->get($item['game_id']);
                abort_if(! $game, 422, 'That game is not part of this week.');
                abort_if(
                    $game->kickoff_at->isPast(),
                    422,
                    "{$game->awayTeam->abbreviation} @ {$game->homeTeam->abbreviation} has already kicked off.",
                );
                abort_unless(
                    in_array($item['picked_team_id'], [$game->home_team_id, $game->away_team_id], true),
                    422,
                    'Invalid team for that game.',
                );
            }

            $existingPicks = Pick::where('pool_id', $pool->id)
                ->where('user_id', $user->id)
                ->where('season', $season)
                ->where('week', $week)
                ->get();

            $lockedGameIds = $games->filter(fn (Game $game) => $game->kickoff_at->isPast())->keys();
            $reservedValues = $existingPicks->whereIn('game_id', $lockedGameIds)->pluck('confidence_value');

            Pick::where('pool_id', $pool->id)
                ->where('user_id', $user->id)
                ->where('season', $season)
                ->where('week', $week)
                ->whereNotIn('game_id', $lockedGameIds)
                ->delete();

            $availableValues = collect(range(1, $totalGames))->diff($reservedValues)->sortDesc()->values();

            abort_if($availableValues->count() < count($rankedItems), 422, 'Not enough confidence values left this week.');

            foreach ($rankedItems as $index => $item) {
                Pick::create([
                    'pool_id' => $pool->id,
                    'user_id' => $user->id,
                    'game_id' => $item['game_id'],
                    'picked_team_id' => $item['picked_team_id'],
                    'confidence_value' => $availableValues[$index],
                    'season' => $season,
                    'week' => $week,
                    'is_auto_assigned' => false,
                ]);
            }

            return Pick::where('pool_id', $pool->id)
                ->where('user_id', $user->id)
                ->where('season', $season)
                ->where('week', $week)
                ->with('game')
                ->get();
        });
    }
}

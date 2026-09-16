<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Pick;
use App\Models\Pool;

class PickLockService
{
    /**
     * For every game in the given season/week that has already kicked off,
     * make sure every active participant has a pick recorded. Anyone who
     * never chose a winner gets auto-assigned the highest confidence value
     * they haven't already used this week and is scored as an automatic
     * incorrect pick — this is what stops a player from dodging a risky
     * game while saving their high confidence values for safer ones later
     * in the week.
     *
     * Idempotent and safe to call from a request (to catch up on a game
     * that just kicked off) as well as from a scheduled job.
     */
    public function lockDueGames(int $season, int $week): int
    {
        $lockedGames = Game::where('season', $season)
            ->where('week', $week)
            ->where('kickoff_at', '<=', now())
            ->orderBy('kickoff_at')
            ->get();

        if ($lockedGames->isEmpty()) {
            return 0;
        }

        $pool = Pool::sole();
        $totalGames = Game::where('season', $season)->where('week', $week)->count();
        $userIds = $pool->participants()->where('is_active', true)->pluck('user_id');

        $autoFilled = 0;

        foreach ($userIds as $userId) {
            $existing = Pick::where('pool_id', $pool->id)
                ->where('user_id', $userId)
                ->where('season', $season)
                ->where('week', $week)
                ->get()
                ->keyBy('game_id');

            $usedValues = $existing->pluck('confidence_value')->all();

            foreach ($lockedGames as $game) {
                if ($existing->has($game->id)) {
                    continue;
                }

                $available = collect(range(1, $totalGames))->diff($usedValues);

                if ($available->isEmpty()) {
                    continue;
                }

                $value = $available->max();
                $usedValues[] = $value;

                Pick::create([
                    'pool_id' => $pool->id,
                    'user_id' => $userId,
                    'game_id' => $game->id,
                    'picked_team_id' => null,
                    'confidence_value' => $value,
                    'season' => $season,
                    'week' => $week,
                    'is_auto_assigned' => true,
                ]);

                $autoFilled++;
            }
        }

        return $autoFilled;
    }
}

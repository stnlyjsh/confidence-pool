<?php

namespace App\Services;

use App\Events\GameScoreUpdated;
use App\Events\StandingsUpdated;
use App\Models\Game;
use App\Models\Pool;
use Closure;
use Throwable;

class GameUpdateBroadcaster
{
    /**
     * Broadcast a game's score/status change and, if it affected anyone's
     * points, a standings refetch signal. Broadcasting is best-effort: if
     * Reverb is unreachable, that must not turn an otherwise-successful
     * mutation (a sync, a void, a manual override) into a failed request —
     * live updates are a nice-to-have, not a correctness requirement.
     */
    public function broadcast(Game $game, bool $scoreChanged, int $updatedPicks): void
    {
        $poolId = Pool::query()->value('id');

        if ($poolId === null) {
            return;
        }

        if ($scoreChanged) {
            $this->safely(fn () => GameScoreUpdated::dispatch(
                $poolId,
                $game->id,
                $game->home_score,
                $game->away_score,
                $game->status->value,
            ));
        }

        if ($updatedPicks > 0) {
            $this->safely(fn () => StandingsUpdated::dispatch($poolId, $game->week));
        }
    }

    private function safely(Closure $dispatch): void
    {
        try {
            $dispatch();
        } catch (Throwable $e) {
            report($e);
        }
    }
}

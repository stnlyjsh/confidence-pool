<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\PickLockService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('picks:lock-due-now')]
#[Description('Auto-fill picks for any recently kicked-off game, across whichever season/week(s) currently have one')]
class LockDuePicksNow extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PickLockService $lockService): int
    {
        // Bounded to recent kickoffs rather than all of history: anything
        // older than this should already be fully locked from a prior run,
        // and this runs every minute so the window only needs to catch
        // whatever just crossed the threshold.
        $weeks = Game::where('kickoff_at', '<=', now())
            ->where('kickoff_at', '>=', now()->subDays(2))
            ->select('season', 'week')
            ->distinct()
            ->get();

        $total = 0;

        foreach ($weeks as $week) {
            $total += $lockService->lockDueGames($week->season, $week->week);
        }

        $this->info("Auto-filled {$total} pick(s) across {$weeks->count()} week(s).");

        return self::SUCCESS;
    }
}

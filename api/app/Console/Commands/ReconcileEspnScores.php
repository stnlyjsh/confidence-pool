<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\Espn\EspnScheduleSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('espn:reconcile')]
#[Description('Re-sync recently played weeks to catch any score ESPN corrected after first reporting it final')]
class ReconcileEspnScores extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EspnScheduleSyncService $sync): int
    {
        // A few days back rather than just "today": score corrections can
        // land more than one nightly run after a game airs.
        $weeks = Game::where('kickoff_at', '>=', now()->subDays(4))
            ->where('kickoff_at', '<=', now())
            ->select('season', 'week')
            ->distinct()
            ->get();

        foreach ($weeks as $week) {
            $sync->sync($week->season, $week->week);
        }

        $this->info("Reconciled {$weeks->count()} week(s).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Espn\EspnScheduleSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('espn:sync {--season=} {--week=}')]
#[Description('Sync NFL schedule and scores from ESPN for a season/week (defaults to whatever ESPN reports as current)')]
class SyncEspnSchedule extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EspnScheduleSyncService $sync): int
    {
        $season = $this->option('season') !== null ? (int) $this->option('season') : null;
        $week = $this->option('week') !== null ? (int) $this->option('week') : null;

        $count = $sync->sync($season, $week);

        $this->info("Synced {$count} games.");

        return self::SUCCESS;
    }
}

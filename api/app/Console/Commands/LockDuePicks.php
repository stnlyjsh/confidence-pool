<?php

namespace App\Console\Commands;

use App\Services\PickLockService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('picks:lock-due {season} {week}')]
#[Description('Auto-fill picks for any game in the given season/week that has already kicked off')]
class LockDuePicks extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PickLockService $lockService): int
    {
        $count = $lockService->lockDueGames((int) $this->argument('season'), (int) $this->argument('week'));

        $this->info("Auto-filled {$count} pick(s).");

        return self::SUCCESS;
    }
}

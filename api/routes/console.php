<?php

use App\Models\Game;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// onOneServer(): idempotent either way, but if the app ever scales past a
// single instance, this stops every replica from polling ESPN/locking picks
// on the same tick and avoids redundant outbound requests.

// Baseline: catches new games, postponements, and schedule changes even
// when nothing is currently live.
Schedule::command('espn:sync')->everyFifteenMinutes()->onOneServer();

// Tight polling only while a game is plausibly in progress, so scores and
// standings feel live without hammering ESPN's unofficial endpoint the
// other 22ish hours a day nothing is happening.
Schedule::command('espn:sync')->everyMinute()->when(fn () => Game::hasLiveGames())->onOneServer();

// Catches any pick left unmade the moment its game kicks off, independent
// of whether a score sync happens to run at the same tick.
Schedule::command('picks:lock-due-now')->everyMinute()->onOneServer();

// ESPN occasionally corrects a final score after the fact; this catches it
// without waiting on someone to reopen the app for that week.
Schedule::command('espn:reconcile')->dailyAt('04:00')->onOneServer();

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
//
// Deliberately not everyMinute(): Laravel Cloud's scheduler wakes the app
// for every cron tick before it can evaluate the when() gate below (that
// gate only runs once the app is already booted), so an everyMinute()
// entry forces a wake every single minute regardless of whether it's
// actually gated off — which defeats scale-to-zero entirely, 24/7/365,
// not just during games.
Schedule::command('espn:sync')->everyFiveMinutes()->when(fn () => Game::hasLiveGames())->onOneServer();

// Catches any pick left unmade shortly after its game kicks off. A few
// minutes of lag before auto-fill is harmless — see the note above on why
// this isn't everyMinute().
Schedule::command('picks:lock-due-now')->everyFiveMinutes()->onOneServer();

// ESPN occasionally corrects a final score after the fact; this catches it
// without waiting on someone to reopen the app for that week.
Schedule::command('espn:reconcile')->dailyAt('04:00')->onOneServer();

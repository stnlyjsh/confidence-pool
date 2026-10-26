<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// onOneServer(): idempotent either way, but if the app ever scales past a
// single instance, this stops every replica from polling ESPN/locking picks
// on the same tick and avoids redundant outbound requests.

// Laravel Cloud's Scale-to-Zero environment wakes the app for every cron
// tick that's due, *before* it can evaluate any ->when() gate — that gate
// only runs once the app is already booted, so it can't stop the wake
// itself. That means wake frequency has to be controlled by the cron
// expression itself, not application logic, and anything scheduled at or
// under our 5-minute sleep timeout is effectively always-on regardless of
// how little work actually happens once awake.
//
// So: tight, every-minute polling only runs during windows pinned to the
// real upcoming schedule (routes/polling-windows.php, regenerated weekly
// by .github/workflows/update-polling-windows.yml from ESPN's actual
// kickoff times and committed as a normal deploy — the only way Cloud
// reliably picks up a changed wake schedule). Being awake for the handful
// of hours a real game is plausibly live is the cost we want to pay;
// everywhere else falls through to the hourly baseline below.
foreach (require __DIR__.'/polling-windows.php' as $cronExpression) {
    Schedule::command('espn:sync')->cron($cronExpression)->onOneServer();
    Schedule::command('picks:lock-due-now')->cron($cronExpression)->onOneServer();
}

// Hourly baseline the rest of the time: catches schedule changes,
// postponements, and anything the generated windows above miss — a flex
// scheduling change, or a week whose kickoff times weren't finalized yet
// (still shown as "TBD" by ESPN) when the windows were last generated.
// picks:lock-due-now rides along on the same tick for free, since the
// wake already happens for espn:sync either way.
Schedule::command('espn:sync')->hourly()->onOneServer();
Schedule::command('picks:lock-due-now')->hourly()->onOneServer();

// ESPN occasionally corrects a final score after the fact; this catches it
// without waiting on someone to reopen the app for that week.
Schedule::command('espn:reconcile')->dailyAt('04:00')->onOneServer();

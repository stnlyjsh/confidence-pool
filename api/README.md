# Confidence Pool API

Laravel JSON API for the confidence pool app (Sanctum bearer auth, Reverb websockets, ESPN schedule/score sync). See the repo root [README](../README.md) and the implementation plan for overall context.

## Local development (Docker / Sail)

```
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

App: http://localhost:8000
Reverb (websockets): ws://localhost:8080

If `docker`/`docker compose` aren't on your PATH but Docker Desktop is installed, symlink the CLI once:

```
ln -sf /Applications/Docker.app/Contents/Resources/bin/docker /opt/homebrew/bin/docker
ln -sf /Applications/Docker.app/Contents/Resources/bin/docker-credential-desktop /opt/homebrew/bin/docker-credential-desktop
```

### Common commands

```
./vendor/bin/sail artisan test --compact
./vendor/bin/sail exec laravel.test ./vendor/bin/pint --format agent
./vendor/bin/sail down          # stop containers
```

## Running processes

Two long-running processes need to be up for the app to behave like production, on top of `sail up -d`:

```
./vendor/bin/sail artisan reverb:start     # websocket server (live scores/standings)
./vendor/bin/sail artisan schedule:work    # runs the scheduled jobs below every minute
```

No queue worker is needed: broadcast events use `ShouldBroadcastNow` (synchronous) rather than
being queued, and the scheduled jobs run as plain commands rather than queued jobs — reasonable
trade-offs at this app's scale, since none of it is high-frequency enough to justify running and
monitoring an extra worker process.

Scheduled jobs (`routes/console.php`, see `sail artisan schedule:list`):

- `espn:sync` every 15 minutes always, plus every minute while `Game::hasLiveGames()` is true
  (a game kicked off in the last 5 hours and isn't final/voided/canceled yet) — cheap the other
  ~22 hours a day nothing is happening, closer to real-time once something is.
- `picks:lock-due-now` every minute — auto-fills any pick left unmade the moment its game kicks
  off, across whichever season/week(s) currently have a game in the last 2 days.
- `espn:reconcile` nightly at 04:00 — re-syncs the last 4 days of weeks in case ESPN corrected a
  score after first reporting it final.

In production, replace `schedule:work` with a real cron entry (`* * * * * php artisan schedule:run`)
and run `reverb:start` under a process supervisor (systemd, supervisor, etc.) instead of a foreground
terminal.

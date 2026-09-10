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

# Confidence Pool

A mobile-focused NFL confidence pool app: Laravel JSON API + React/Vite PWA.

See [.claude/plans](../../.claude/plans) (or ask Claude) for the full implementation plan, or `docs/` for the ERD and manual test plan as they're written.

## Layout

- `api/` — Laravel backend (JSON API, Sanctum auth, Reverb websockets, ESPN schedule/score sync).
- `pwa/` — React + TypeScript + Vite PWA frontend.
- `docs/` — design notes and manual test plan.

## Local development

### PWA (`pwa/`)

```
cd pwa
cp .env.example .env
npm install
npm run dev
```

### API (`api/`)

Requires Docker Desktop (Laravel Sail). Setup instructions land here once Phase 0 backend scaffolding is complete.

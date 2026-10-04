# CLAUDE.md

Read `SPEC.md` before starting any task. It defines the scope, architecture, data model and milestones.

## Conventions

- Laravel 13, PHP 8.3+, Inertia + React + TypeScript, Tailwind, Pest.
- `app/Simulation/` is pure PHP: no Eloquent, no DB, no facades, no `now()`, no `rand()`. Pass everything in through DTOs and use `SeededRng` for all randomness.
- All tunable numbers live in `config/market/*.php`. Never hard-code economic values in the engine.
- Store money as integer cents. Format it only in the UI.
- Write Pest tests alongside every engine class. Use fixed seeds in tests.
- Run `php artisan test` before finishing a task, and don't finish with failing tests.
- Keep controllers thin: they map between models and DTOs and call the engine.

## Data rules

- Never add scraping code for property portals or Google.
- Never write per-listing data from portals into the repo.
- Geo data comes from committed files in `database/seeders/geo/`. Don't fetch it at runtime.
- Show OpenStreetMap attribution wherever map tiles or OSM-derived data appear.

## When you finish a milestone

Summarise what was built, what's tested, any placeholder values you added to config, and anything in `SPEC.md` that turned out to be unclear.

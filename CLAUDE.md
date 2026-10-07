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

## Working notes

- Develop on the branch `claude/beautiful-hopper-1pigih`. Stage one (milestones 1–9) is done; stage two is planned in `SPEC.md` §11 (milestones 10–13 done).
- Realism comes before fun: this is a simulation. Calibrate to real data, record the source next to each number, and say plainly when a value is a guess. `docs/balance-report.md` explains every calibration so far; rerun `php artisan market:balance` (and `--years=5`, and `--daily` for the day-by-day engine the game uses) after changing the economy, and keep its targets passing.
- Data the user pastes from Google or property portals goes in only as aggregated distributions (percentiles, tiers), never as individual listings, streets or prices.
- Real time: the nightly run needs the scheduler and a queue worker (`php artisan schedule:work` and `php artisan queue:work`), or run it by hand with `php artisan game:nightly --sync`.
- Push notifications need VAPID keys in `.env` (`php artisan webpush:vapid` prints them) and HTTPS (or localhost); without keys the app works but sends nothing.
- The user runs the app on Windows (`C:\SITES\Traspaso\Traspaso`, portable PHP 8.4). After pulling they usually need `php artisan migrate` and `npm run build`; say so whenever a change needs either.

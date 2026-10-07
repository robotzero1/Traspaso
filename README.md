# Traspaso

A business simulator: take over a café in Zaragoza and try to make it work
for a year. See [SPEC.md](SPEC.md) for the design.

> Business locations, financial data and operating characteristics are
> simulated. Real-world market data is used to establish realistic ranges
> and distributions.

## Running it locally

You need PHP 8.3+ (with the `sqlite3`, `pdo_sqlite`, `mbstring` and `intl`
extensions), Composer and Node 22.

```bash
git clone <this repo> traspaso && cd traspaso
composer setup      # installs, creates the SQLite database, migrates, loads the geo data, builds the frontend
composer dev        # serves the app at http://localhost:8000 (plus Vite and the queue)
```

Open http://localhost:8000, register an account, and click **Games** to
start one. Email verification is required: in local development the
verification email goes to the log (`storage/logs/laravel.log`); copy the
link from there. Or create a verified user straight away:

```bash
php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'me@example.com']);"
# then log in as me@example.com with the password: password
```

### On Windows

- Use a PHP build with a `php.ini`: copy `php.ini-development` to `php.ini`
  in the PHP folder and enable `extension=curl`, `fileinfo`, `intl`,
  `mbstring`, `openssl`, `pdo_sqlite`, `sqlite3` and `zip`.
- For `php artisan geo:fetch` (or anything else that downloads over HTTPS),
  PHP needs trusted certificate authorities: download
  https://curl.se/ca/cacert.pem and set `curl.cainfo` and `openssl.cafile`
  in `php.ini` to its full path.
- If `composer dev` fails (its log viewer needs a Unix-only extension), run
  `php artisan serve` instead. `composer setup` has already built the
  frontend, so that's all you need.

## Useful commands

```bash
php artisan test                 # the whole test suite (Pest)
php artisan market:placeholders  # which market parameters still need real research
php artisan market:balance       # play 1,000 games per scripted strategy and report how they end
php artisan market:balance --years=5   # the same over five years, with a survival curve
```

`market:balance` plays on whatever geo data is seeded; see
`docs/balance-report.md` for the latest results and what they led to.

## The viability check

`/viability` (no account needed): pin a café on the map, enter the listing's
figures, and it runs 1,000 simulated five-year futures on the queue (about half
a minute; keep `php artisan queue:work` running). The full report is meant to
be paid for; for local testing put `VIABILITY_UNLOCK_ALL=true` in `.env`.

## Push notifications and the app

The game can be installed as an app (Settings → Notifications → Install, or
the browser's "Add to Home Screen") and sends each night's results as a push
notification. Push needs a key pair, generated once:

```bash
php artisan webpush:vapid   # prints WEBPUSH_PUBLIC_KEY / WEBPUSH_PRIVATE_KEY / WEBPUSH_SUBJECT for .env
```

Then turn notifications on for each device in Settings → Notifications.
Browsers only allow push on HTTPS or `localhost`; on iPhone the app must be
added to the home screen first. Without keys the game works but sends nothing.

## Map and real geo data

The map uses OpenStreetMap tiles, loaded by your browser
(© OpenStreetMap contributors).

Out of the box, neighbourhood areas, landmarks and business positions are
approximate placeholders. To build the real data (district boundaries,
points of interest and the footfall surface) from OpenStreetMap, on a
machine with internet access:

```bash
php artisan geo:fetch    # downloads OSM data for the city (a few minutes)
php artisan geo:build    # derives the files in database/seeders/geo/zaragoza (a minute or two)
php artisan db:seed --class=NeighbourhoodSeeder
php artisan db:seed --class=PointOfInterestSeeder
php artisan db:seed --class=FootfallPointSeeder
```

Then start a new game. Fill in `database/seeders/geo/zaragoza/sources/population.csv`
with real district populations before building, and see
[database/seeders/geo/zaragoza/README.md](database/seeders/geo/zaragoza/README.md)
for calibrating footfall against your own pedestrian counts.

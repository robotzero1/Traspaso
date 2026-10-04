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
- If `composer dev` fails (its log viewer needs a Unix-only extension), run
  `php artisan serve` instead. `composer setup` has already built the
  frontend, so that's all you need.

## Useful commands

```bash
php artisan test                 # the whole test suite (Pest)
php artisan market:placeholders  # which market parameters still need real research
```

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

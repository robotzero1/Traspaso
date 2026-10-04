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

## Map

The map uses OpenStreetMap tiles, loaded by your browser
(© OpenStreetMap contributors). Neighbourhood areas, landmarks and business
positions are approximate placeholders until the real geo data is imported
(SPEC.md milestone 8).

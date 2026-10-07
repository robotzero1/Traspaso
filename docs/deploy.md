# Going live

How to run Traspaso as a public service on one small server. It's sized for a
start: one VPS, SQLite, the database queue. Move to MySQL/Postgres and Redis
only when the load calls for it.

## The server

- A VPS in the EU (GDPR: keep personal data in the EU), e.g. 2 vCPU / 4 GB,
  Ubuntu 24.04. The viability check uses one CPU for about 30 s per report.
- Install: nginx, PHP 8.4 (`php8.4-fpm php8.4-sqlite3 php8.4-mbstring
  php8.4-xml php8.4-curl php8.4-gmp php8.4-bcmath php8.4-intl php8.4-zip`;
  `gmp` makes push faster), Composer, Node 22, supervisor, certbot, git.
- Clone into `/var/www/traspaso`, owned by `www-data`.

## First install

```bash
cd /var/www/traspaso
cp .env.example .env            # then edit, see below
composer install --no-dev --optimize-autoloader
php artisan key:generate
touch database/database.sqlite
php artisan migrate --force --seed   # loads the committed geo data (no test user in production)
npm ci && npm run build
php artisan webpush:vapid        # paste its three lines into .env
php artisan optimize
```

Then the config files in `deploy/`:

| File | Where it goes | What it does |
|---|---|---|
| `deploy/nginx.conf` | `/etc/nginx/sites-available/traspaso` | the site; run `certbot --nginx` for HTTPS |
| `deploy/supervisor.conf` | `/etc/supervisor/conf.d/traspaso.conf` | keeps the queue worker running |
| `deploy/crontab` | `crontab -u www-data deploy/crontab` | runs the scheduler every minute |
| `deploy/deploy.sh` | run it to ship an update | pull, build, migrate, restart the worker |

HTTPS is required: push notifications and installing the app only work on it.

## .env for production

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://traspaso.example
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=sqlite
DB_BUSY_TIMEOUT=5000
DB_JOURNAL_MODE=wal
DB_SYNCHRONOUS=normal

SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database

# Password resets and email verification need real mail.
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_FROM_ADDRESS=hola@traspaso.example

STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_TAX_RATE_ID=txr_...

WEBPUSH_PUBLIC_KEY=...
WEBPUSH_PRIVATE_KEY=...
WEBPUSH_SUBJECT=mailto:hola@traspaso.example

NIGHTLY_PING_URL=https://hc-ping.com/...   # optional, see Monitoring
TRUSTED_PROXIES=                            # * if behind Cloudflare or a load balancer
```

Leave `GAME_FAST_FORWARD` and `VIABILITY_UNLOCK_ALL` unset: both are off in
production. Run `php artisan optimize` again after every `.env` change.

In Stripe, add a webhook endpoint `https://traspaso.example/stripe/webhook`
for `checkout.session.completed` and `checkout.session.async_payment_succeeded`.

## Monitoring

- **Uptime and health:** point an uptime monitor (Better Stack, UptimeRobot…)
  at `https://traspaso.example/up`. It returns 500 when the database is down,
  a queued job has waited over 30 minutes (no worker), a job failed in the
  last 24 hours, or a café missed a nightly run (no scheduler). On the server,
  `php artisan app:health` prints the same checks.
- **The nightly run:** set `NIGHTLY_PING_URL` to a heartbeat check
  (healthchecks.io, free) expecting one ping a day around 23:00 Madrid; it
  alerts when a night doesn't happen.
- **Errors:** logs are in `storage/logs/` (one file a day, kept 14 days).
  `php artisan queue:failed` lists failed jobs; `queue:retry all` re-runs them.

## Backups

`app:backup` runs every night at 04:00 Madrid: a consistent copy of the SQLite
database, compressed, in `storage/app/backups/`, keeping 14 days
(`BACKUP_PATH`, `BACKUP_KEEP_DAYS`). A backup on the same disk isn't one: copy
the folder off the server daily too, e.g. with rclone to an EU object store:

```bash
# in www-data's crontab, after the backup
30 4 * * * rclone copy /var/www/traspaso/storage/app/backups remote:traspaso-backups --max-age 48h
```

Turn on the host's own snapshots as well. To restore: stop the worker,
`gunzip` a backup over `database/database.sqlite`, start the worker. Test a
restore once before launch.

## GDPR basics

- **Access and portability:** Settings → Profile → *Download my data*
  (JSON: account, games, viability checks, purchases, push devices).
- **Erasure:** deleting the account removes the user, their games and push
  devices. Purchase records stay, without the user, because tax law requires
  them (six years); their reports stay reachable by link but lose the owner.
- **Retention:** unpaid viability reports are deleted after 90 days, and
  checkouts never paid after 30 (`model:prune`, daily); failed jobs after 30
  days; backups after 14.
- **Cookies:** only essential ones (session, CSRF, the appearance setting), so
  no consent banner is needed. Adding analytics would change that.
- **Still to do by you:** fill in the [placeholders] in the terms, privacy and
  disclaimer pages and have a lawyer check them; keep a record of processing
  (RAT) and sign the hosting provider's and Stripe's data processing
  agreements.

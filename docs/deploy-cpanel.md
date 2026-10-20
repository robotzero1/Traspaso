# Testing on a cPanel server

How to put Traspaso on ordinary cPanel hosting, for testing. It differs from
`docs/deploy.md` (your own server) in three ways: no root, no process manager
to keep a queue worker running, and often no Node to build the frontend. The
fixes: the scheduler works the queue each minute (`QUEUE_FROM_SCHEDULER`),
and you build the frontend on your PC and upload it.

Replace `USER` with your cPanel username and `traspaso.example.com` with
your domain throughout.

## 1. Check PHP

cPanel → **MultiPHP Manager** (or **Select PHP Version** on CloudLinux):
choose **PHP 8.3 or 8.4** for the domain, with these extensions on:
`pdo_sqlite`, `sqlite3`, `mbstring`, `intl`, `bcmath`, `fileinfo`,
`openssl`, `curl`, and `gmp` (optional, makes push notifications faster).

The command line can use a different PHP from the website. In
**Terminal** (or SSH), run `php -v`. If it's older than 8.3, use the full
path instead of `php` everywhere below, e.g.
`/opt/cpanel/ea-php84/root/usr/bin/php` (cPanel EasyApache) or
`/opt/alt/php84/usr/bin/php` (CloudLinux).

## 2. A subdomain pointing at `public/`

cPanel → **Domains** → **Create a New Domain**: `traspaso.example.com`,
untick "Share document root", and set the document root to
`traspaso/public`. The app then lives in `/home/USER/traspaso`, outside
`public_html`, and only `public/` is on the web.

Then cPanel → **SSL/TLS Status** → run **AutoSSL** for the subdomain.
HTTPS is needed for login cookies in production, push notifications,
installing the app and "Use my location" on the counting page.

## 3. Get the code

cPanel → **Git Version Control** → **Create**:

- Clone URL: `https://github.com/robotzero1/Traspaso.git`
- Repository path: `/home/USER/traspaso` (the folder the subdomain's
  document root is inside; if cPanel created it in step 2, delete the empty
  folder first, or clone before creating the subdomain)
- Repository name: Traspaso

If the repository is **private**, cPanel needs a key: in **Terminal** run
`ssh-keygen -t ed25519 -f ~/.ssh/github_traspaso -N ""`, add
`~/.ssh/github_traspaso.pub` to GitHub (repo → Settings → Deploy keys), add to
`~/.ssh/config`:

```
Host github.com
    IdentityFile ~/.ssh/github_traspaso
```

and clone `git@github.com:robotzero1/Traspaso.git` instead.

After cloning, choose the **main** branch (Manage → Checked-Out Branch).

## 4. Install

In **Terminal**:

```bash
cd ~/traspaso
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
```

If `composer` isn't found: `curl -sS https://getcomposer.org/installer | php`,
then use `php composer.phar install --no-dev --optimize-autoloader`.

Edit `.env` (cPanel **File Manager**, show hidden files, or `nano .env`):

```ini
APP_NAME=Traspaso
APP_ENV=production
APP_DEBUG=false
APP_URL=https://traspaso.example.com

DB_CONNECTION=sqlite
DB_DATABASE=/home/USER/traspaso/database/database.sqlite
DB_BUSY_TIMEOUT=5000
DB_JOURNAL_MODE=wal

SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
# No supervisor on cPanel: the scheduler works the queue each minute.
QUEUE_FROM_SCHEDULER=true
LOG_STACK=daily

# For testing: the fast-forward button, and the full viability report
# without paying. Remove both for real players.
GAME_FAST_FORWARD=true
VIABILITY_UNLOCK_ALL=true

# Email verification and password resets: a mailbox from cPanel → Email
# Accounts. Without it, set MAIL_MAILER=log and verify users by hand (step 6).
MAIL_MAILER=smtp
MAIL_HOST=mail.example.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=hola@example.com
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=hola@example.com
```

Then load the database (tables plus the committed Zaragoza geo data; no test
user is created in production) and cache the config:

```bash
php artisan migrate --force --seed
php artisan optimize
```

Run `php artisan optimize` again after every `.env` change.

## 5. The frontend

The built frontend (`public/build`) isn't in git. Either:

- **Build on your PC and upload** (simplest): on Windows, in
  `C:\SITES\Traspaso\Traspaso`, run `npm run build`, zip the `public\build`
  folder, upload it with **File Manager** to `/home/USER/traspaso/public/`
  and extract it there, so you have `public/build/manifest.json`.
- **Or build on the server**, if it has Node 22 (cPanel → **Setup Node.js
  App** on CloudLinux gives you one: create an app with root `traspaso`,
  copy the "enter the virtual environment" command it shows, run it in
  Terminal, then `npm ci && npm run build`).

## 6. The scheduler (nightly run, queue, backups)

cPanel → **Cron Jobs** → add, every minute (`* * * * *`):

```
cd /home/USER/traspaso && php artisan schedule:run >> /dev/null 2>&1
```

(with the full PHP path from step 1 if needed). This plays every café's day
at 23:00 Madrid time, works the queue each minute (the nightly jobs and the
viability checks), and runs the 04:00 backup.

If your host only allows a cron every 5 or 15 minutes, it still works: the
nightly run starts within that time of 23:00, and a viability check waits
up to that long to start.

**A test account without email:** register on the site, then in Terminal:

```bash
php artisan tinker --execute="App\Models\User::where('email','you@example.com')->update(['email_verified_at' => now()]);"
```

Production requires passwords of 12+ characters with upper and lower case,
a number and a symbol.

## 7. Push notifications (optional)

```bash
php artisan webpush:vapid    # prints three lines: paste them into .env
php artisan optimize
```

## 8. Check it

- Open `https://traspaso.example.com/up`: it should say the app is up.
- `php artisan app:health` checks the database, the queue and the nightly
  run.
- Errors: `storage/logs/laravel-YYYY-MM-DD.log`.
- A blank page or "Vite manifest not found": the frontend isn't uploaded
  (step 5).
- 500 errors right after install: `storage/` and `bootstrap/cache/` must be
  writable (755 folders is normal on cPanel).

## Updating

1. Push to `main` on GitHub.
2. cPanel → **Git Version Control** → Manage → **Pull or Deploy** →
   **Update from Remote**.
3. In Terminal:

   ```bash
   cd ~/traspaso
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan optimize
   ```

4. If the frontend changed: build on your PC and upload `public/build` again
   (step 5).

A Stripe webhook, if you test payments, goes to
`https://traspaso.example.com/stripe/webhook` (see the README).

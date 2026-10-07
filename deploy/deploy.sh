#!/usr/bin/env bash
# Run on the server, as www-data, from /var/www/traspaso, to ship what's on the branch.
set -euo pipefail

php artisan down --retry=30 || true
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up

#!/bin/sh
set -e

cd /var/www/html

# ── Sanity check ──────────────────────────────────────────────────────────────
if [ ! -f vendor/autoload.php ]; then
  echo "[init] ERROR: vendor/autoload.php not found."
  echo "[init] Run 'composer install' on the host before starting the container."
  exit 1
fi

# ── Wait for MySQL when DB_ADAPTER=mysql ──────────────────────────────────────
if [ "${DB_ADAPTER:-sqlite}" = "mysql" ]; then
  echo "[init] Waiting for MySQL at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
  until php -r "
    try {
      \$pdo = new PDO(
        'mysql:host=${DB_HOST:-mysql};port=${DB_PORT:-3306};dbname=${DB_NAME:-nene_vault}',
        '${DB_USER:-nene_vault}',
        '${DB_PASSWORD:-nene_vault}'
      );
      echo 'OK';
    } catch (Exception \$e) { exit(1); }
  " 2>/dev/null; do
    printf '.'
    sleep 2
  done
  echo ""
  echo "[init] MySQL ready."
fi

# ── Apply schema / migrations ──────────────────────────────────────────────────
if [ "${DB_ADAPTER:-sqlite}" = "sqlite" ]; then
  # phinx appends .sqlite3 to the filename, mismatching the app's PDO DSN.
  # Use the idempotent CREATE TABLE IF NOT EXISTS bootstrap script instead.
  echo "[init] Bootstrapping SQLite schema..."
  mkdir -p var && chmod 775 var
  php docker/bootstrap-schema.php
  # Apache (www-data) needs write access for WAL journal files
  chown -R www-data:www-data var/ 2>/dev/null || true
  chmod -R 664 var/*.sqlite 2>/dev/null || true
else
  echo "[init] Running MySQL migrations (env: ${DB_ENV:-local})..."
  php vendor/bin/phinx migrate -c phinx.php -e "${DB_ENV:-local}"
fi

# ── Seed initial org + user (idempotent) ─────────────────────────────────────
echo "[init] Seeding initial data..."
php docker/seed-initial.php

echo "[init] Starting Apache on port 8080..."
exec apache2-foreground

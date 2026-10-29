#!/usr/bin/env bash
# Runs on website-mahes after a Jobmatch release tarball is uploaded.
set -euo pipefail

APP_ROOT="${APP_ROOT:-/var/www/saas-hr}"
RELEASES="$APP_ROOT/releases"
SHARED="$APP_ROOT/shared"
KEEP="${KEEP_RELEASES:-5}"
TS="$(date +%Y%m%d%H%M%S)"
RELEASE="$RELEASES/$TS"

TARBALL=""
for candidate in /tmp/jobmatch-release.tar.gz /tmp/saas-hr-release.tar.gz; do
  if [ -f "$candidate" ]; then
    TARBALL="$candidate"
    break
  fi
done
if [ -z "$TARBALL" ]; then
  echo "ERROR: release tarball not found in /tmp"
  exit 1
fi

mkdir -p "$RELEASES" "$SHARED/storage" "$SHARED/database"
mkdir -p "$SHARED/storage/"{app/public,framework/{cache,sessions,views},logs}

echo "== extracting $TARBALL to $RELEASE =="
mkdir -p "$RELEASE"
# macOS tar may embed AppleDouble xattrs; ignore unknown keywords
tar --warning=no-unknown-keyword -xzf "$TARBALL" -C "$RELEASE" 2>/dev/null \
  || tar -xzf "$TARBALL" -C "$RELEASE" || true
# fail only if artisan missing
test -f "$RELEASE/artisan"

# shared links
rm -rf "$RELEASE/storage"
ln -sfn "$SHARED/storage" "$RELEASE/storage"
ln -sfn "$SHARED/.env" "$RELEASE/.env"
if [ -f "$SHARED/database/database.sqlite" ]; then
  mkdir -p "$RELEASE/database"
  ln -sfn "$SHARED/database/database.sqlite" "$RELEASE/database/database.sqlite"
fi

# keep production brand in sync
if [ -f "$SHARED/.env" ]; then
  sed -i 's|^APP_NAME=.*|APP_NAME=Jobmatch|' "$SHARED/.env" || true
  sed -i 's|^VITE_APP_NAME=.*|VITE_APP_NAME=Jobmatch|' "$SHARED/.env" || true
  grep -q '^APP_NAME=' "$SHARED/.env" || echo 'APP_NAME=Jobmatch' >> "$SHARED/.env"
  grep -q '^VITE_APP_NAME=' "$SHARED/.env" || echo 'VITE_APP_NAME=Jobmatch' >> "$SHARED/.env"
fi

cd "$RELEASE"
export PATH="$HOME/.bun/bin:/usr/local/bin:$PATH"

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

php artisan migrate --force
php artisan db:seed --force || true
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true

# atomic switch (replace dir/symlink cleanly)
TMP_LINK="$APP_ROOT/current.new"
ln -sfn "$RELEASE" "$TMP_LINK"
rm -rf "$APP_ROOT/current"
mv "$TMP_LINK" "$APP_ROOT/current"
sudo systemctl reload php8.4-fpm nginx

# prune old releases
cd "$RELEASES"
ls -1dt */ 2>/dev/null | tail -n +"$((KEEP + 1))" | xargs -r rm -rf

echo "== deployed Jobmatch $TS =="
curl -fsS -o /dev/null -w "health:%{http_code}\n" http://127.0.0.1/ || true

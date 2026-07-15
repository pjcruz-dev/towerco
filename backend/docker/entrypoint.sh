#!/bin/sh
set -e

cd /var/www/html

# Apply tuned OPcache config at boot so existing images pick it up without a rebuild.
if [ -f docker/opcache.ini ]; then
  target_dir="$(php -r 'echo PHP_CONFIG_FILE_SCAN_DIR ?: "/usr/local/etc/php/conf.d";' 2>/dev/null || echo /usr/local/etc/php/conf.d)"
  if [ -d "$target_dir" ] && [ -w "$target_dir" ]; then
    cp docker/opcache.ini "$target_dir/zz-toweros-opcache.ini" 2>/dev/null || true
  fi
fi

if [ -f docker/php-uploads.ini ]; then
  target_dir="$(php -r 'echo PHP_CONFIG_FILE_SCAN_DIR ?: "/usr/local/etc/php/conf.d";' 2>/dev/null || echo /usr/local/etc/php/conf.d)"
  if [ -d "$target_dir" ] && [ -w "$target_dir" ]; then
    cp docker/php-uploads.ini "$target_dir/zz-toweros-uploads.ini" 2>/dev/null || true
  fi
fi

# Seed .env once; do not overwrite on every start (would rotate APP_KEY and break encrypted SSO secrets).
if [ "${TOWEROS_DOCKER:-0}" = "1" ] && [ -f .env.docker ] && [ ! -f .env ]; then
  cp .env.docker .env
fi

if [ ! -f vendor/autoload.php ]; then
  echo "[api] Installing Composer dependencies..."
  composer install --no-interaction --prefer-dist
fi

wait_for_mysql() {
  echo "[api] Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
  until php -r "
    try {
      new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD')
      );
      exit(0);
    } catch (Throwable \$e) {
      exit(1);
    }
  " 2>/dev/null; do
    sleep 2
  done
  echo "[api] MySQL is ready."
}

wait_for_mysql

# APP_KEY must be `APP_KEY=base64:...` in backend/.env (bind-mounted). Bare `APP_KEY` or empty breaks encryption.
ensure_app_key() {
  # Docker may inject APP_KEY= (empty) from an old container create; that blocks .env and key:generate.
  unset APP_KEY

  if [ ! -f .env ]; then
    echo "[api] Error: backend/.env missing. Copy backend/.env.docker to backend/.env on the host, then restart."
    exit 1
  fi

  if grep -qE '^APP_KEY=base64:' .env 2>/dev/null; then
    APP_KEY_FROM_FILE=$(grep -E '^APP_KEY=' .env | head -n1 | cut -d= -f2- | tr -d '\r')
    export APP_KEY="$APP_KEY_FROM_FILE"
    return 0
  fi

  echo "[api] APP_KEY missing or invalid in .env — generating (one-time)..."
  sed -i '/^APP_KEY$/d' .env 2>/dev/null || true
  sed -i '/^APP_KEY=$/d' .env 2>/dev/null || true
  sed -i '/^APP_KEY=/d' .env 2>/dev/null || true
  if ! grep -qE '^APP_KEY=' .env 2>/dev/null; then
    echo 'APP_KEY=' >> .env
  fi
  unset APP_KEY
  php artisan key:generate --force --no-interaction
  APP_KEY_FROM_FILE=$(grep -E '^APP_KEY=' .env | head -n1 | cut -d= -f2- | tr -d '\r')
  export APP_KEY="$APP_KEY_FROM_FILE"
}

ensure_app_key

# Framework boot optimization. Cache config + events for faster per-request boot.
# route:cache is intentionally skipped: the app has closure routes (web.php, tenant.php).
# Set TOWEROS_API_OPTIMIZE=0 to keep hot-reload of config in active development.
if [ "${TOWEROS_API_OPTIMIZE:-1}" = "1" ]; then
  echo "[api] Optimizing boot: config:cache + event:cache (set TOWEROS_API_OPTIMIZE=0 to disable)"
  php artisan config:clear --no-interaction 2>/dev/null || true
  php artisan config:cache --no-interaction 2>/dev/null || echo "[api] Warning: config:cache failed; using runtime config."
  php artisan event:cache --no-interaction 2>/dev/null || true
else
  php artisan config:clear --no-interaction 2>/dev/null || true
fi

if [ "${TOWEROS_DOCKER_AUTO_MIGRATE:-1}" = "1" ]; then
  echo "[api] Running central migrations..."
  php artisan migrate --force --no-interaction || {
    echo "[api] Warning: central migrate failed (may already be applied). Continuing..."
  }
  if [ "${TOWEROS_DOCKER_MIGRATE_TENANTS:-0}" = "1" ]; then
    echo "[api] Running tenant migrations..."
    php artisan tenants:migrate --force --no-interaction || echo "[api] Warning: tenant migrate failed (missing tenant DBs?). Run toweros:migrate after fixing tenants."
  fi
fi

if [ ! -f storage/oauth-private.key ]; then
  echo "[api] Installing Passport keys..."
  php artisan passport:keys --force --no-interaction 2>/dev/null || true
fi

# Passport requires 600/660 on oauth keys; avoid chmod -R on storage (sets 777 on bind mounts).
for key in storage/oauth-private.key storage/oauth-public.key; do
  if [ -f "$key" ]; then
    chmod 600 "$key" 2>/dev/null || chmod 660 "$key" 2>/dev/null || true
  fi
done

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rwx storage/framework storage/logs bootstrap/cache 2>/dev/null || true

echo "[api] Laravel API http://0.0.0.0:8000"

WORKERS="${TOWEROS_API_WORKERS:-4}"
if [ "$WORKERS" -gt 1 ] && command -v nginx >/dev/null 2>&1; then
  echo "[api] Starting ${WORKERS} PHP workers behind nginx (concurrent dev requests)"
  rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true

  {
    echo "upstream toweros_api_workers {"
    i=1
    while [ "$i" -le "$WORKERS" ]; do
      echo "    server 127.0.0.1:$((8000 + i));"
      i=$((i + 1))
    done
    echo "}"
    echo "server {"
    echo "    listen 8000;"
    echo "    server_name _;"
    echo "    client_max_body_size 64m;"
    echo "    location / {"
    echo "        proxy_http_version 1.1;"
    echo "        proxy_set_header Host \$http_host;"
    echo "        proxy_set_header X-Real-IP \$remote_addr;"
    echo "        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;"
    echo "        proxy_set_header X-Forwarded-Proto \$scheme;"
    echo "        proxy_read_timeout 300s;"
    echo "        proxy_pass http://toweros_api_workers;"
    echo "    }"
    echo "}"
  } > /etc/nginx/conf.d/toweros-api.conf

  i=1
  while [ "$i" -le "$WORKERS" ]; do
    worker_port=$((8000 + i))
    php artisan serve --host=127.0.0.1 --port="$worker_port" --no-reload &
    i=$((i + 1))
  done
  exec nginx -g 'daemon off;'
fi

echo "[api] Single-worker mode (set TOWEROS_API_WORKERS=4 for concurrent requests)"
exec php artisan serve --host=0.0.0.0 --port=8000

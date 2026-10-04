#!/bin/sh
set -e

git config --global --add safe.directory /var/www/html

echo "🔧 Starting Laravel Octane in LOCAL/DEVELOPMENT mode..."

# Sync vendor from backup if empty (for bind mount scenarios)
if [ ! -f "vendor/autoload.php" ] && [ -d "/opt/vendor-backup" ]; then
  echo "📦 Syncing vendor from image backup..."
  cp -r /opt/vendor-backup/* vendor/ 2>/dev/null || true
  echo "✅ Vendor synced successfully"
fi

# Generate APP_KEY if missing or empty in .env file
if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
  echo "⚙️ Generating APP_KEY..."
  php artisan key:generate --ansi
else
  echo "✅ APP_KEY already exists"
fi

# Clear all caches
echo "🧹 Clearing all caches..."
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan event:clear || true

# Wait for database
echo "⏳ Waiting for database connection..."
MAX_RETRIES=5
RETRY_COUNT=0

until php artisan db:show 2>/dev/null; do
  RETRY_COUNT=$((RETRY_COUNT + 1))
  if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
    echo "❌ Database connection timeout after ${MAX_RETRIES} retries"
    echo "⚠️ Starting without database connection..."
    break
  fi
  echo "⏳ Database not ready, retrying... (${RETRY_COUNT}/${MAX_RETRIES})"
  sleep 2
done

if [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
  echo "✅ Database connected successfully"

  # Run migrations
  echo "🔄 Running database migrations..."
  php artisan migrate --force || echo "⚠️ Migrations failed, continuing..."

  # Seed database (optional)
  if [ "${DB_SEED:-false}" = "true" ]; then
    echo "🌱 Seeding database..."
    php artisan db:seed --force || echo "⚠️ Seeding failed, continuing..."
  fi
fi

# Create storage link
echo "🔗 Creating storage link..."
php artisan storage:link || true

# Update GeoIP database in background (non-blocking, silent fail)
if [ -n "${MAXMIND_LICENSE_KEY:-}" ]; then
  echo "🌍 Updating GeoIP database in background..."
  nohup sh -c 'php artisan geoip:update 2>/dev/null || true' >/dev/null 2>&1 &
fi

# Regenerate autoload (for development changes)
echo "📦 Regenerating composer autoload..."
composer dump-autoload --optimize --quiet || true

# Package discovery
echo "📋 Running package discovery..."
php artisan package:discover --ansi || true

# IDE helper dar in prozhe nasb nist — seda zadanesh faghat log-e alaki misakht.

# Show application info
echo "
╔═════════════════════════════════════════════════════════════╗
║  Laravel Development Environment Ready                      ║
╠═════════════════════════════════════════════════════════════╣
║  App:       http://localhost:${OCTANE_PORT:-8000}           ║
║  Vite:      http://localhost:${VITE_PORT:-5173}             ║
║  Telescope: http://localhost:${OCTANE_PORT:-8000}/telescope ║
║  Horizon:   http://localhost:${OCTANE_PORT:-8000}/horizon   ║
║  Pulse:     http://localhost:${OCTANE_PORT:-8000}/pulse     ║
╠═════════════════════════════════════════════════════════════╣
║  Xdebug: ${XDEBUG_MODE:-off}                                ║
╚═════════════════════════════════════════════════════════════╝
"

# Build frontend assets automatically (once). Runs on first boot; skipped afterwards.
if [ ! -d node_modules ] || [ ! -f public/build/manifest.json ]; then
  echo "📦 Installing npm deps & building frontend assets..."
  ( npm install --no-audit --no-fund && npm run build ) || echo "⚠️ Asset build skipped (will fall back gracefully)"
fi

export NODE_PATH=/var/www/html/node_modules

# Optional hot-reload watch mode — opt-in via OCTANE_WATCH=true and only if chokidar is present
OCTANE_ARGS="--server=swoole --host=0.0.0.0 --port=8000 \
    --workers=${OCTANE_WORKERS:-1} \
    --task-workers=${OCTANE_TASK_WORKERS:-1} \
    --max-requests=${OCTANE_MAX_REQUESTS:-500}"

if [ "${OCTANE_WATCH:-false}" = "true" ] && node -e "require.resolve('chokidar')" 2>/dev/null; then
  echo "🚀 Starting Octane with hot reload (watch)..."
  exec php artisan octane:start $OCTANE_ARGS --watch
else
  echo "🚀 Starting Octane..."
  exec php artisan octane:start $OCTANE_ARGS
fi

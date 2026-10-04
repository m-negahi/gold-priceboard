#!/bin/sh
set -e

log() { echo "[$(date +%H:%M:%S)] $1"; }

trap 'log "Shutting down..."; php artisan octane:stop 2>/dev/null || true; exit 0' SIGTERM SIGINT

log "Starting Laravel Octane Production..."

# Clear stale cache
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php bootstrap/cache/routes*.php 2>/dev/null || true
php artisan config:clear 2>/dev/null || true

# Wait for database
log "Waiting for database..."
RETRIES=30
until php artisan db:show >/dev/null 2>&1; do
    RETRIES=$((RETRIES - 1))
    [ $RETRIES -eq 0 ] && { log "Database timeout!"; exit 1; }
    sleep 2
done
log "Database connected"

# Run migrations if AUTO_MIGRATE is enabled
if [ "${AUTO_MIGRATE:-false}" = "true" ]; then
    log "Running migrations..."
    php artisan migrate --force --no-interaction || { log "Migration failed!"; exit 1; }
    log "Migrations completed"
fi

# Ensure the public storage symlink exists (تصاویرِ محصول/مقاله زیرِ /storage → storage/app/public)
php artisan storage:link --force >/dev/null 2>&1 || log "storage:link skipped"

# Sync-e static-haye image be volume-ye moshtarak (service-ye `static`/nginx serve mikonad).
# Aval dar masir-e movaghat copy → bad swap, ta nginx dar tool-e sync 404 nadahad.
# Fail-e sync nabayad app ra bekoshad (set -e) — nginx dar badtarin halat
# nosakhe-ye ghabli ra serve mikonad; faghat warning log mishavad.
if [ -d /public-assets ]; then
    log "Syncing static assets to shared volume..."
    sync_failed=0
    for d in build images fonts; do
        if [ -d "public/$d" ]; then
            {
                rm -rf "/public-assets/.$d.new" &&
                cp -a "public/$d" "/public-assets/.$d.new" &&
                rm -rf "/public-assets/$d" &&
                mv "/public-assets/.$d.new" "/public-assets/$d"
            } || sync_failed=1
        fi
    done
    [ -f public/favicon.ico ] && { cp -f public/favicon.ico /public-assets/favicon.ico || sync_failed=1; }
    if [ "$sync_failed" = "1" ]; then
        log "⚠ Static asset sync FAILED (permission? volume root-owned?) — continuing with previous assets"
    else
        log "Static assets synced"
    fi
fi

# Optimize
log "Optimizing..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache 2>/dev/null || true

# ---- Production guardrails ------------------------------------------------
# .env محلی OCTANE_WORKERS=1 دارد؛ اگر همان به سرور کپی شود سایت با «یک» worker
# سریال می‌شود و زیر ~۲۰ کاربر همزمان از پا درمی‌آید. اینجا clamp می‌کنیم.
CPUS=$(nproc 2>/dev/null || echo 2)
DEFAULT_WORKERS=$((CPUS * 2))
[ "$DEFAULT_WORKERS" -gt 16 ] && DEFAULT_WORKERS=16

WORKERS="${OCTANE_WORKERS:-$DEFAULT_WORKERS}"
if [ "${APP_ENV:-}" = "production" ] && [ "$WORKERS" -lt 2 ]; then
    log "⚠ OCTANE_WORKERS=$WORKERS is a dev value — overriding to $DEFAULT_WORKERS for production"
    WORKERS=$DEFAULT_WORKERS
fi

if [ "${APP_ENV:-}" != "production" ]; then
    log "⚠⚠ APP_ENV='${APP_ENV:-unset}' (NOT production) — debug pages/telescope may be exposed!"
fi
[ "${APP_DEBUG:-false}" = "true" ] && log "⚠⚠ APP_DEBUG=true in a deployed container — stack traces will leak env values!"
# Telescope/Pulse در production همیشه خاموش — per-request overhead سنگین دارند
# و .env محلی آن‌ها را true دارد. (env-var بر config چیره است.)
if [ "${APP_ENV:-}" = "production" ]; then
    if [ "${TELESCOPE_ENABLED:-false}" = "true" ] || [ "${PULSE_ENABLED:-false}" = "true" ]; then
        log "⚠ TELESCOPE/PULSE were enabled — force-disabling for production"
    fi
    export TELESCOPE_ENABLED=false PULSE_ENABLED=false
fi
# ---------------------------------------------------------------------------

# OPcache preload — پیش‌فرض **خاموش**. علتِ خاموشی: preloadِ گسترده‌ی
# Illuminate کلاسِ Collection/stdClass را در حالتِ «unlinked/incomplete» جا
# می‌گذاشت (همان صدها هشدارِ «Can't preload unlinked class» در استارت) و هر
# جایی که Livewire یک آبجکتِ framework را در snapshotش unserialize می‌کرد
# (مثلاً داشبوردِ Pulse) با ۵۰۰ می‌شکست: «incomplete object … before
# unserialize()». صرفه‌ی ~۱۰٪ preload ارزشِ این ریسکِ سراسری را ندارد.
# فقط با OCTANE_PRELOAD=true (و preload.php ی مرتب‌شده) روشن شود.
if [ "${OCTANE_PRELOAD:-false}" = "true" ] && [ -f bootstrap/preload.php ]; then
    mkdir -p /tmp/php-ini
    echo "opcache.preload=/var/www/html/bootstrap/preload.php" > /tmp/php-ini/zz-preload.ini
    export PHP_INI_SCAN_DIR="/usr/local/etc/php/conf.d:/tmp/php-ini"
    log "OPcache preload enabled (bootstrap/preload.php)"
fi

# Start
log "Starting Octane (workers: ${WORKERS}, task: ${OCTANE_TASK_WORKERS:-2})"
exec php artisan octane:start \
    --server=swoole \
    --host=0.0.0.0 \
    --port=8000 \
    --workers="${WORKERS}" \
    --task-workers="${OCTANE_TASK_WORKERS:-2}" \
    --max-requests="${OCTANE_MAX_REQUESTS:-1000}"

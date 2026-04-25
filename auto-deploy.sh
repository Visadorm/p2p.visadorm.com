#!/bin/bash

# ============================================
# Auto Deploy Script — Visadorm P2P
# Checks GitHub for new commits every 2 minutes.
# Only deploys if new changes detected.
# Trigger 6.
# ============================================
BRANCH="main"

PROJECT_DIR="/home/visadorm/p2p.visadorm.com"
DOMAIN="p2p.visadorm.com"
LOG_DIR="$PROJECT_DIR/storage/logs"
TG_A="8725383408"
TG_B=":AAFRWW7t1Sopj"
TG_C="ZFIxwgNTq5rFu0Vj-wtpzw"
TG_BOT="${TG_A}${TG_B}${TG_C}"
TG_CHAT="6113315629"

send_tg() {
    curl -s -X POST "https://api.telegram.org/bot${TG_BOT}/sendMessage" \
      --data-urlencode "chat_id=${TG_CHAT}" \
      --data-urlencode "parse_mode=HTML" \
      --data-urlencode "text=$1" > /dev/null 2>&1 || true
}

mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/auto-deploy.log"

cd "$PROJECT_DIR"

git fetch origin "$BRANCH" --quiet

LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse "origin/$BRANCH")

if [ "$LOCAL" = "$REMOTE" ]; then
    exit 0
fi

DEPLOY_START=$(date +%s)
DEPLOY_START_FMT=$(date '+%Y-%m-%d %H:%M:%S')

echo "" >> "$LOG_FILE"
echo "=== Auto-deploy started at $DEPLOY_START_FMT ===" >> "$LOG_FILE"
echo "Local:  $LOCAL" >> "$LOG_FILE"
echo "Remote: $REMOTE" >> "$LOG_FILE"

exec >> "$LOG_FILE" 2>&1

git stash 2>/dev/null || true
git pull origin "$BRANCH"

FILES_CHANGED=$(git diff HEAD@{1} --name-only 2>/dev/null | wc -l | tr -d ' ')

# Always run composer install — idempotent if up-to-date, recovers from
# missed/failed installs on prior deploys (e.g. when composer.lock landed
# in a commit that didn't fully install).
echo "Installing PHP dependencies..."
COMPOSER_OUT=$(composer install --no-dev --no-interaction --optimize-autoloader 2>&1)
COMPOSER_RC=$?
echo "$COMPOSER_OUT"
if [ $COMPOSER_RC -eq 0 ]; then
    COMPOSER_STATUS="Ok"
    COMPOSER_ERR=""
else
    COMPOSER_STATUS="Failed (rc=$COMPOSER_RC)"
    COMPOSER_ERR=$(echo "$COMPOSER_OUT" | tail -c 600 | tr '<>' '[]')
fi

# ===== Force-seed required settings rows via bare PDO (no Laravel boot) =====
# Bypasses Laravel boot. Filament/Inertia would otherwise crash via Spatie
# MissingSettings before any artisan command can run.
SETTINGS_BACKFILL_STATUS="Skipped"
SETTINGS_BACKFILL_ERR=""
if [ -f "scripts/backfill-settings.php" ]; then
    BACKFILL_OUT=$(php scripts/backfill-settings.php 2>&1)
    BACKFILL_RC=$?
    echo "Settings backfill: $BACKFILL_OUT"
    if [ $BACKFILL_RC -eq 0 ]; then
        SETTINGS_BACKFILL_STATUS="Ran"
    else
        SETTINGS_BACKFILL_STATUS="Failed (rc=$BACKFILL_RC)"
        SETTINGS_BACKFILL_ERR=$(echo "$BACKFILL_OUT" | tail -c 400 | tr '<>' '[]')
    fi
fi

MIGRATE_OUTPUT=$(php artisan migrate --force --no-interaction 2>&1)
MIGRATE_RC=$?
echo "$MIGRATE_OUTPUT"
MIGRATE_TAIL=$(echo "$MIGRATE_OUTPUT" | tail -c 500 | tr '<>' '[]')
if [ $MIGRATE_RC -ne 0 ]; then
    MIGRATE_STATUS="Failed (rc=$MIGRATE_RC)"
elif echo "$MIGRATE_OUTPUT" | grep -q "Migrating\|migrated"; then
    MIGRATE_STATUS="Ran"
else
    MIGRATE_STATUS="Nothing to migrate"
fi

# Always refresh autoload + clear ALL Laravel caches before seeders.
# Stale bootstrap/cache/services.php can carry an old AppServiceProvider that
# pre-dates the defensive settings-boot hook, causing Spatie MissingSettings.
composer dump-autoload --optimize --no-interaction 2>&1 || true
php artisan clear-compiled 2>&1 || true
php artisan optimize:clear 2>&1 || true
php artisan package:discover --ansi 2>&1 || true

# ===== BEGIN ONE-SHOT: world_seed =====
# Seeds nnjeim/world countries/states/cities/timezones/currencies/languages once.
# Remove this block after first successful run on production.
WORLD_SEED_MARKER="$PROJECT_DIR/storage/app/.world_seeded"
WORLD_SEED_STATUS="Skipped"
WORLD_SEED_ERR=""
if [ ! -f "$WORLD_SEED_MARKER" ]; then
    echo "Running WorldSeeder (one-shot)..."
    WS_OUT=$(php -d memory_limit=1024M artisan db:seed --class=WorldSeeder --force --no-interaction 2>&1)
    WS_RC=$?
    echo "$WS_OUT"
    if [ $WS_RC -eq 0 ]; then
        touch "$WORLD_SEED_MARKER"
        WORLD_SEED_STATUS="Ran"
    else
        WORLD_SEED_STATUS="Failed"
        WORLD_SEED_ERR=$(echo "$WS_OUT" | tail -c 500 | tr '<>' '[]')
    fi
fi
# ===== END ONE-SHOT: world_seed =====

# ===== BEGIN ONE-SHOT: pages_seed =====
# Seeds default Terms + Privacy pages once.
# Remove this block after first successful run on production.
PAGES_SEED_MARKER="$PROJECT_DIR/storage/app/.pages_seeded"
PAGES_SEED_STATUS="Skipped"
PAGES_SEED_ERR=""
if [ ! -f "$PAGES_SEED_MARKER" ]; then
    echo "Running PagesSeeder (one-shot)..."
    PS_OUT=$(php artisan db:seed --class=PagesSeeder --force --no-interaction 2>&1)
    PS_RC=$?
    echo "$PS_OUT"
    if [ $PS_RC -eq 0 ]; then
        touch "$PAGES_SEED_MARKER"
        PAGES_SEED_STATUS="Ran"
    else
        PAGES_SEED_STATUS="Failed"
        PAGES_SEED_ERR=$(echo "$PS_OUT" | tail -c 500 | tr '<>' '[]')
    fi
fi
# ===== END ONE-SHOT: pages_seed =====

php artisan optimize:clear
php artisan optimize
php artisan filament:optimize
php artisan queue:restart

chmod -R 775 storage bootstrap/cache
chmod -R 755 public/

# Purge Cloudflare cache
CLOUDFLARE_API_TOKEN=$(grep '^CLOUDFLARE_API_TOKEN=' .env 2>/dev/null | cut -d'=' -f2 || true)
CLOUDFLARE_API_TOKEN="${CLOUDFLARE_API_TOKEN:-cfat_moNoQPL0lI4H6ITyw2ruADDXpEA3s2sj6eo1opwNa31e61ec}"
CF_STATUS="Skipped"
if [ -n "$CLOUDFLARE_API_TOKEN" ]; then
    CF_RESULT=$(curl -s -X POST "https://api.cloudflare.com/client/v4/zones/6281abc53c8094f3973eb93c956c49d5/purge_cache" \
      -H "Authorization: Bearer $CLOUDFLARE_API_TOKEN" \
      -H "Content-Type: application/json" \
      --data '{"purge_everything":true}' 2>&1 || true)
    if echo "$CF_RESULT" | grep -q '"success":true'; then
        CF_STATUS="Purged"
    else
        CF_STATUS="Failed"
    fi
    echo "Cloudflare: $CF_STATUS"
fi

DEPLOY_END=$(date +%s)
DEPLOY_END_FMT=$(date '+%Y-%m-%d %H:%M:%S')
DURATION=$((DEPLOY_END - DEPLOY_START))
echo "=== Auto-deploy completed at $DEPLOY_END_FMT (${DURATION}s) ==="

# Telegram notification
SERVER_HOST=$(hostname 2>/dev/null || echo "unknown")
SERVER_IP=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "unknown")
COMMIT_MSG=$(git log -1 --pretty=format:'%s' 2>/dev/null)
COMMIT_SHORT=$(git rev-parse --short HEAD 2>/dev/null)

MSG="<b>Deploy Complete</b>

<b>Domain:</b> ${DOMAIN}
<b>Server:</b> ${SERVER_HOST} (${SERVER_IP})
<b>Branch:</b> ${BRANCH}
<b>Commit:</b> <code>${COMMIT_SHORT}</code> ${COMMIT_MSG}
<b>Files:</b> ${FILES_CHANGED} changed
<b>Composer:</b> ${COMPOSER_STATUS}
<b>SettingsBackfill:</b> ${SETTINGS_BACKFILL_STATUS}
<b>Migration:</b> ${MIGRATE_STATUS}
<b>WorldSeed:</b> ${WORLD_SEED_STATUS}
<b>PagesSeed:</b> ${PAGES_SEED_STATUS}
<b>Cloudflare:</b> ${CF_STATUS}
<b>Duration:</b> ${DURATION}s"

if [ -n "$COMPOSER_ERR" ]; then
    MSG="${MSG}

<b>Composer error:</b>
<pre>${COMPOSER_ERR}</pre>"
fi

if [ -n "$SETTINGS_BACKFILL_ERR" ]; then
    MSG="${MSG}

<b>SettingsBackfill error:</b>
<pre>${SETTINGS_BACKFILL_ERR}</pre>"
fi

if [ "$MIGRATE_STATUS" != "Ran" ] && [ "$MIGRATE_STATUS" != "Nothing to migrate" ]; then
    MSG="${MSG}

<b>Migrate output:</b>
<pre>${MIGRATE_TAIL}</pre>"
fi

if [ -n "$WORLD_SEED_ERR" ]; then
    MSG="${MSG}

<b>WorldSeed error:</b>
<pre>${WORLD_SEED_ERR}</pre>"
fi

if [ -n "$PAGES_SEED_ERR" ]; then
    MSG="${MSG}

<b>PagesSeed error:</b>
<pre>${PAGES_SEED_ERR}</pre>"
fi

send_tg "$MSG"


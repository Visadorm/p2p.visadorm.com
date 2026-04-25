#!/bin/bash

# ============================================
# Auto Deploy Script — Visadorm P2P
# Checks GitHub for new commits every 2 minutes.
# Only deploys if new changes detected.
# Trigger.
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

if git diff HEAD@{1} --name-only 2>/dev/null | grep -q "composer.lock"; then
    echo "Installing PHP dependencies..."
    composer install --no-dev --no-interaction --optimize-autoloader
fi

MIGRATE_OUTPUT=$(php artisan migrate --force --no-interaction 2>&1)
echo "$MIGRATE_OUTPUT"
if echo "$MIGRATE_OUTPUT" | grep -q "Migrating\|migrated"; then
    MIGRATE_STATUS="Ran"
else
    MIGRATE_STATUS="Skipped"
fi

# Always refresh autoload + clear caches before seeders (composer may have just installed)
composer dump-autoload --optimize --no-interaction 2>&1 || true
php artisan config:clear 2>&1 || true

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
<b>Migration:</b> ${MIGRATE_STATUS}
<b>WorldSeed:</b> ${WORLD_SEED_STATUS}
<b>PagesSeed:</b> ${PAGES_SEED_STATUS}
<b>Cloudflare:</b> ${CF_STATUS}
<b>Duration:</b> ${DURATION}s"

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


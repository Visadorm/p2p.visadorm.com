#!/bin/bash
set -e

# ============================================
# Manual Deploy Script — Visadorm P2P
# Usage: bash deploy-pull.sh
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

DEPLOY_START=$(date +%s)
DEPLOY_START_FMT=$(date '+%Y-%m-%d %H:%M:%S')

mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/deploy.log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo ""
echo "=== Deploy started at $DEPLOY_START_FMT ==="
cd "$PROJECT_DIR"

echo "[1/9] Stashing local changes..."
git stash 2>/dev/null || true

echo "[2/9] Pulling from $BRANCH..."
git pull origin "$BRANCH"

FILES_CHANGED=$(git diff HEAD@{1} --name-only 2>/dev/null | wc -l | tr -d ' ')

echo "[3/9] Installing PHP dependencies..."
if git diff HEAD@{1} --name-only 2>/dev/null | grep -q "composer.lock"; then
    composer install --no-dev --no-interaction --optimize-autoloader
else
    echo "       No composer changes, skipping..."
fi

echo "[4/9] Running migrations..."
MIGRATE_OUTPUT=$(php artisan migrate --force --no-interaction 2>&1)
echo "$MIGRATE_OUTPUT"
if echo "$MIGRATE_OUTPUT" | grep -q "Migrating\|migrated"; then
    MIGRATE_STATUS="Ran"
else
    MIGRATE_STATUS="Skipped"
fi

echo "[5/9] Clearing caches..."
php artisan optimize:clear

echo "[6/9] Rebuilding caches..."
php artisan optimize
php artisan filament:optimize

echo "[7/9] Restarting queue workers..."
php artisan queue:restart

echo "[8/9] Setting permissions..."
chmod -R 775 storage bootstrap/cache
chmod -R 755 public/

echo "[9/9] Purging Cloudflare cache..."
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
    echo "       Cloudflare: $CF_STATUS"
else
    echo "       CLOUDFLARE_API_TOKEN not set, skipping"
fi

DEPLOY_END=$(date +%s)
DEPLOY_END_FMT=$(date '+%Y-%m-%d %H:%M:%S')
DURATION=$((DEPLOY_END - DEPLOY_START))
echo "=== Deploy completed at $DEPLOY_END_FMT (${DURATION}s) ==="

# Telegram notification
SERVER_HOST=$(hostname 2>/dev/null || echo "unknown")
SERVER_IP=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "unknown")
COMMIT_MSG=$(git log -1 --pretty=format:'%s' 2>/dev/null)
COMMIT_SHORT=$(git rev-parse --short HEAD 2>/dev/null)

MSG="<b>Deploy Complete (manual)</b>

<b>Domain:</b> ${DOMAIN}
<b>Server:</b> ${SERVER_HOST} (${SERVER_IP})
<b>Branch:</b> ${BRANCH}
<b>Commit:</b> <code>${COMMIT_SHORT}</code> ${COMMIT_MSG}
<b>Files:</b> ${FILES_CHANGED} changed
<b>Migration:</b> ${MIGRATE_STATUS}
<b>Cloudflare:</b> ${CF_STATUS}
<b>Duration:</b> ${DURATION}s"

send_tg "$MSG"
echo ""

#!/bin/bash

# ============================================
# Auto Deploy Script — Visadorm P2P
# Checks GitHub for new commits every 2 minutes
# Only deploys if new changes detected
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

php artisan optimize:clear
php artisan optimize
php artisan filament:optimize
php artisan queue:restart

# ============================================================
# BEGIN ONE-SHOT — Strict Escrow Redeployment (2026-04-18)
# Updates blockchain_settings to new contract addresses deployed
# on Base Sepolia. Idempotent via marker file. REMOVE THIS BLOCK
# ON NEXT PUSH after Telegram confirms "ONE-SHOT: ran successfully".
# ============================================================
ONESHOT_MARKER="$PROJECT_DIR/storage/.oneshot-2026-04-18-strict-escrow"
ONESHOT_STATUS="Skipped"
if [ ! -f "$ONESHOT_MARKER" ]; then
    echo "Running one-shot: update blockchain_settings to new addresses..."
    ONESHOT_OUTPUT=$(php artisan tinker --execute="
\$s = app(\App\Settings\BlockchainSettings::class);
\$s->trade_escrow_address = '0xc4D74Ddcc4ee8DFa9687C37De8be3A21f813C00D';
\$s->soulbound_nft_address = '0xD81a5b95550E94C7ec995af6BaaD4ab7281B5FFD';
\$s->usdc_address = '0xe3B1038eecea95053256D0e5d52D11A0703D1c4F';
\$s->save();
echo 'ok';
" 2>&1)
    echo "$ONESHOT_OUTPUT"
    if echo "$ONESHOT_OUTPUT" | grep -q "ok"; then
        touch "$ONESHOT_MARKER"
        ONESHOT_STATUS="Ran"
        php artisan optimize:clear
        php artisan optimize
    else
        ONESHOT_STATUS="Failed"
    fi
fi
# ============================================================
# END ONE-SHOT — Strict Escrow Redeployment
# ============================================================

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
<b>One-Shot:</b> ${ONESHOT_STATUS}
<b>Cloudflare:</b> ${CF_STATUS}
<b>Duration:</b> ${DURATION}s"

send_tg "$MSG"


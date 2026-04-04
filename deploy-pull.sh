#!/bin/bash
set -e

# ============================================
# Manual Deploy Script — Visadorm P2P
# Usage: bash deploy-pull.sh
# ============================================
BRANCH="main"

PROJECT_DIR="/home/visadorm/p2p.visadorm.com"
LOG_DIR="$PROJECT_DIR/storage/logs"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/deploy.log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo ""
echo "=== Deploy started at $TIMESTAMP ==="
cd "$PROJECT_DIR"

echo "[1/8] Stashing local changes..."
git stash 2>/dev/null || true

echo "[2/8] Pulling from $BRANCH..."
git pull origin "$BRANCH"

echo "[3/8] Installing PHP dependencies..."
if git diff HEAD@{1} --name-only 2>/dev/null | grep -q "composer.lock"; then
    composer install --no-dev --no-interaction --optimize-autoloader
else
    echo "       No composer changes, skipping..."
fi

echo "[4/8] Running migrations..."
php artisan migrate --force --no-interaction

echo "[5/8] Clearing caches..."
php artisan optimize:clear

echo "[6/8] Rebuilding caches..."
php artisan optimize
php artisan filament:optimize
php artisan icons:cache

echo "[7/8] Restarting queue workers..."
php artisan queue:restart

echo "[8/9] Setting permissions..."
chmod -R 775 storage bootstrap/cache
chmod -R 755 public/

echo "[9/9] Purging Cloudflare cache..."
CLOUDFLARE_API_TOKEN=$(grep '^CLOUDFLARE_API_TOKEN=' .env 2>/dev/null | cut -d'=' -f2 || true)
CLOUDFLARE_API_TOKEN="${CLOUDFLARE_API_TOKEN:-cfat_moNoQPL0lI4H6ITyw2ruADDXpEA3s2sj6eo1opwNa31e61ec}"
if [ -n "$CLOUDFLARE_API_TOKEN" ]; then
    curl -s -X POST "https://api.cloudflare.com/client/v4/zones/6281abc53c8094f3973eb93c956c49d5/purge_cache" \
      -H "Authorization: Bearer $CLOUDFLARE_API_TOKEN" \
      -H "Content-Type: application/json" \
      --data '{"purge_everything":true}' > /dev/null 2>&1 || true
    echo "       Cloudflare cache purged"
else
    echo "       CLOUDFLARE_API_TOKEN not set, skipping"
fi

echo "=== Deploy completed at $(date '+%Y-%m-%d %H:%M:%S') ==="
echo ""

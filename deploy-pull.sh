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

echo "[8/8] Setting permissions..."
chmod -R 775 storage bootstrap/cache
chmod -R 755 public/

echo "=== Deploy completed at $(date '+%Y-%m-%d %H:%M:%S') ==="
echo ""

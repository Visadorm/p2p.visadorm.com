#!/bin/bash

# ============================================
# Auto Deploy Script — Visadorm P2P
# Checks GitHub for new commits every 2 minutes
# Only deploys if new changes detected
# ============================================
BRANCH="main"

PROJECT_DIR="/home/visadorm/p2p.visadorm.com"
LOG_DIR="$PROJECT_DIR/storage/logs"

mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/auto-deploy.log"

cd "$PROJECT_DIR"

git fetch origin "$BRANCH" --quiet

LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse "origin/$BRANCH")

if [ "$LOCAL" = "$REMOTE" ]; then
    exit 0
fi

echo "" >> "$LOG_FILE"
echo "=== Auto-deploy started at $(date '+%Y-%m-%d %H:%M:%S') ===" >> "$LOG_FILE"
echo "Local:  $LOCAL" >> "$LOG_FILE"
echo "Remote: $REMOTE" >> "$LOG_FILE"

exec >> "$LOG_FILE" 2>&1

git stash 2>/dev/null || true
git pull origin "$BRANCH"

if git diff HEAD@{1} --name-only 2>/dev/null | grep -q "composer.lock"; then
    echo "Installing PHP dependencies..."
    composer install --no-dev --no-interaction --optimize-autoloader
fi

php artisan migrate --force --no-interaction
php artisan optimize:clear
php artisan optimize
php artisan filament:optimize
php artisan icons:cache
php artisan merchants:recalculate-stats
php artisan queue:restart

chmod -R 775 storage bootstrap/cache
chmod -R 755 public/

echo "=== Auto-deploy completed at $(date '+%Y-%m-%d %H:%M:%S') ==="

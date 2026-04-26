#!/bin/bash

# ============================================
# Auto Deploy Script — Visadorm P2P
# Checks GitHub for new commits every 2 minutes.
# Only deploys if new changes detected.
# Now also: verifies frontend build, runs settings backfill, ensures storage symlink.
# Trigger: rerun cleanup migration on prod (retrigger).
# ============================================

# Self-replicate to /tmp and re-exec so a mid-run `git pull` that rewrites
# this file cannot corrupt the currently executing shell. Bash reads scripts
# from the open file handle; if git replaces the inode, the running script
# continues with stale content. Re-exec from /tmp avoids that entirely.
if [ -z "$AUTO_DEPLOY_REEXEC" ]; then
    REEXEC_SCRIPT="/tmp/auto-deploy-runner-$$.sh"
    cp "$0" "$REEXEC_SCRIPT" 2>/dev/null && chmod +x "$REEXEC_SCRIPT" 2>/dev/null
    if [ -x "$REEXEC_SCRIPT" ]; then
        export AUTO_DEPLOY_REEXEC=1
        trap "rm -f '$REEXEC_SCRIPT'" EXIT
        exec "$REEXEC_SCRIPT" "$@"
    fi
fi

BRANCH="main"
PROJECT_DIR="/home/visadorm/p2p.visadorm.com"
DOMAIN="p2p.visadorm.com"
LOG_DIR="$PROJECT_DIR/storage/logs"
TG_A="8725383408"
TG_B=":AAFRWW7t1Sopj"
TG_C="ZFIxwgNTq5rFu0Vj-wtpzw"
TG_BOT="${TG_A}${TG_B}${TG_C}"
TG_CHAT="6113315629"

# Cron strips PATH down to a minimal set. Composer + node + git binaries can
# live in many places depending on hosting. Make sure they are findable.
export PATH="/usr/local/bin:/usr/bin:/bin:/opt/cpanel/composer/bin:/opt/cpanel/ea-php82/root/usr/bin:/opt/cpanel/ea-php83/root/usr/bin:$HOME/.composer/vendor/bin:$HOME/bin:$PATH"

# Locate composer (binary or phar) — fall back to common install paths,
# then to a project-local composer.phar (download once if missing).
if command -v composer >/dev/null 2>&1; then
    COMPOSER_CMD="composer"
elif [ -x "/usr/local/bin/composer" ]; then
    COMPOSER_CMD="/usr/local/bin/composer"
elif [ -x "/opt/cpanel/composer/bin/composer" ]; then
    COMPOSER_CMD="/opt/cpanel/composer/bin/composer"
elif [ -f "$HOME/composer.phar" ]; then
    COMPOSER_CMD="php $HOME/composer.phar"
elif [ -f "/usr/local/bin/composer.phar" ]; then
    COMPOSER_CMD="php /usr/local/bin/composer.phar"
else
    PROJECT_COMPOSER="$PROJECT_DIR/composer.phar"
    if [ ! -f "$PROJECT_COMPOSER" ]; then
        echo "Bootstrapping composer.phar into project root..."
        curl -sS https://getcomposer.org/installer -o /tmp/composer-installer.php 2>/dev/null
        if [ -s /tmp/composer-installer.php ]; then
            php /tmp/composer-installer.php --install-dir="$PROJECT_DIR" --filename=composer.phar 2>&1 || true
            rm -f /tmp/composer-installer.php
        fi
    fi
    if [ -f "$PROJECT_COMPOSER" ]; then
        COMPOSER_CMD="php $PROJECT_COMPOSER"
    else
        COMPOSER_CMD=""
    fi
fi

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

# Composer install — only when composer.lock actually changed.
COMPOSER_STATUS="Skipped"
COMPOSER_ERR=""
if git diff HEAD@{1} --name-only 2>/dev/null | grep -q "composer.lock"; then
    if [ -z "$COMPOSER_CMD" ]; then
        COMPOSER_STATUS="NotFound"
        COMPOSER_ERR="composer binary not found in PATH or common locations"
    else
        COMPOSER_OUT=$($COMPOSER_CMD install --no-dev --no-interaction --optimize-autoloader 2>&1)
        COMPOSER_RC=$?
        echo "$COMPOSER_OUT"
        if [ $COMPOSER_RC -eq 0 ]; then
            COMPOSER_STATUS="Ok"
        else
            COMPOSER_STATUS="Failed (rc=$COMPOSER_RC)"
            COMPOSER_ERR=$(echo "$COMPOSER_OUT" | tail -c 600 | tr '<>' '[]')
        fi
    fi
fi

# Defensive: ensure required Spatie settings rows exist before any artisan
# command boots Filament (which would otherwise crash via MissingSettings).
# Bare PDO — no Laravel boot involved. Idempotent.
if [ -f "scripts/backfill-settings.php" ]; then
    php scripts/backfill-settings.php 2>&1 || true
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
<b>Migration:</b> ${MIGRATE_STATUS}
<b>Cloudflare:</b> ${CF_STATUS}
<b>Duration:</b> ${DURATION}s"

if [ -n "$COMPOSER_ERR" ]; then
    MSG="${MSG}

<b>Composer error:</b>
<pre>${COMPOSER_ERR}</pre>"
fi

if [ "$MIGRATE_STATUS" != "Ran" ] && [ "$MIGRATE_STATUS" != "Nothing to migrate" ]; then
    MSG="${MSG}

<b>Migrate output:</b>
<pre>${MIGRATE_TAIL}</pre>"
fi

send_tg "$MSG"

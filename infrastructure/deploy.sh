#!/usr/bin/env bash
#
# AktienKI production deploy — git-based. Run as root on the server.
#
#   /home/aktienki/deploy.sh              deploy the tip of the beta branch
#   /home/aktienki/deploy.sh beta-0.1     deploy / roll back to a tag or commit
#
# The canonical copy lives in the repo at infrastructure/deploy.sh; the running
# copy is /home/aktienki/deploy.sh (outside the working tree so a checkout can't
# swap it mid-run). Keep the two in sync.

set -euo pipefail

REPO=/home/aktienki/AktienKI
APP="$REPO/laravel"
BRANCH=feature/sprint-18.4b
APP_USER=aktienki
FPM=php8.5-fpm
SERVICES=(aktienki-reverb aktienki-market-rest-poll aktienki-market-stream aktienki-queue aktienki-backtest-worker aktienki-scheduler)
HEALTH_URL=https://aktienki.com/

TARGET="${1:-origin/$BRANCH}"
ts() { date -Is; }
log() { printf '\n\033[1;36m>>> %s\033[0m\n' "$*"; }
as_app() { sudo -u "$APP_USER" env -C "$1" "${@:2}"; }

[ "$(id -u)" -eq 0 ] || { echo "run as root"; exit 1; }

# one-time / idempotent: make the worktree root owned by the app user so git
# does not trip over "dubious ownership".
chown "$APP_USER":"$APP_USER" "$REPO" 2>/dev/null || true

log "Deploy $TARGET  ($(ts))"
PREV="$(as_app "$REPO" git rev-parse HEAD)"
echo "$PREV" > /home/aktienki/.last-deploy
echo "  previous HEAD: $PREV   (rollback: $0 $PREV)"

log "Maintenance mode ON"
as_app "$APP" php artisan down --retry=15 --secret="deploy-$(date +%s)" || true
restore_up() { as_app "$APP" php artisan up || true; }
trap restore_up EXIT

log "Fetch + checkout"
as_app "$REPO" git fetch origin --tags --prune
as_app "$REPO" git reset --hard "$TARGET"
as_app "$REPO" git --no-pager log --oneline -1

log "Composer (production)"
as_app "$APP" composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

log "Database migrations"
as_app "$APP" php artisan migrate --force

log "Frontend build (vite)"
as_app "$APP" npm ci --no-audit --no-fund
as_app "$APP" npm run build

log "Framework caches"
as_app "$APP" php artisan optimize:clear
as_app "$APP" php artisan config:cache
as_app "$APP" php artisan route:cache
as_app "$APP" php artisan view:cache
as_app "$APP" php artisan event:cache
as_app "$APP" php artisan storage:link --force

log "Permissions"
chown -R "$APP_USER":www-data "$APP/storage" "$APP/bootstrap/cache"
chmod -R ug+rwX "$APP/storage" "$APP/bootstrap/cache"

log "Reload PHP-FPM + restart workers"
systemctl reload "$FPM"
as_app "$APP" php artisan queue:restart
for s in "${SERVICES[@]}"; do systemctl restart "$s" || echo "  warn: $s restart failed"; done
sleep 3
systemctl is-active "${SERVICES[@]}" | paste -sd' ' -

log "Maintenance mode OFF"
as_app "$APP" php artisan up
trap - EXIT

log "Smoke test"
code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "$HEALTH_URL" || true)"
echo "  $HEALTH_URL -> $code"
if [ "$code" != "200" ]; then
  echo "  SMOKE TEST FAILED — consider: $0 $PREV"
  exit 1
fi

log "Deployed $(as_app "$REPO" git rev-parse --short HEAD) OK  ($(ts))"
echo "  rollback if needed:  $0 $PREV"

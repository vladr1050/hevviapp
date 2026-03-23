#!/usr/bin/env bash
#
# Деплой на production с локальной машины (Mac/Linux).
# Документация: docs/deployment/DEPLOY_HEVVI_PRODUCTION.md
#
set -euo pipefail

# --- настрой под свой сервер ---
DEPLOY_HOST="${DEPLOY_HOST:-root@37.27.188.238}"
DEPLOY_PATH="${DEPLOY_PATH:-/var/www/frpc_hevii-php-backoffice-service}"
COMPOSE_FILE="${COMPOSE_FILE:-compose.yaml:compose.prod.yaml}"

# Каталог репозитория = каталог, где лежит этот скрипт (../ от scripts/)
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

echo "==> Deploy from: $ROOT_DIR"
echo "==> Target:      $DEPLOY_HOST:$DEPLOY_PATH"
echo "==> Compose:     $COMPOSE_FILE"

cd "$ROOT_DIR"

if [[ "${SKIP_RSYNC:-0}" != "1" ]]; then
  echo "==> rsync (excluding .env, node_modules, .git)..."
  rsync -az \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='var/cache/*' \
    --exclude='var/log/*' \
    --exclude='.env' \
    --exclude='.env.local' \
    --exclude='.DS_Store' \
    ./ "${DEPLOY_HOST}:${DEPLOY_PATH}/"
else
  echo "==> SKIP_RSYNC=1 — rsync пропущен"
fi

echo "==> Remote: composer, npm build, migrate, cache..."
ssh "$DEPLOY_HOST" "cd ${DEPLOY_PATH} && export COMPOSE_FILE=${COMPOSE_FILE} && \
  docker compose exec -T php composer install --no-dev --optimize-autoloader --no-interaction && \
  docker compose exec -T php sh -c 'cd /var/www/app && npm install && npm run build' && \
  docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction && \
  docker compose exec -T php php bin/console cache:clear --env=prod --no-warmup && \
  docker compose restart php nginx && \
  sleep 3 && \
  docker compose exec -T php php bin/console cache:warmup --env=prod"

echo "==> Готово: https://hevvi.app (обнови страницу с Cmd+Shift+R при смене фронта)"

#!/usr/bin/env bash
#
# Авто-восстановление прода: если сайт не отвечает локально (зависший PHP-FPM),
# перезапускает php + nginx. Запускается по cron на сервере.
# Документация: docs/deployment/RESILIENCE.md
#
# Установка (на сервере, один раз):
#   cp scripts/hevvi-healthcheck.sh /usr/local/bin/hevvi-healthcheck.sh
#   chmod +x /usr/local/bin/hevvi-healthcheck.sh
#   echo '*/5 * * * * root /usr/local/bin/hevvi-healthcheck.sh' > /etc/cron.d/hevvi-healthcheck
#
set -uo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/var/www/frpc_hevii-php-backoffice-service}"
COMPOSE="docker compose -f compose.yaml -f compose.prod.yaml"
# Локальная проверка через docker-nginx (минуем Caddy/TLS). Host из trusted_hosts.
CHECK_URL="${CHECK_URL:-http://127.0.0.1:8090/}"
CHECK_HOST_HEADER="${CHECK_HOST_HEADER:-www.hevvi.app}"
MAX_TIME="${MAX_TIME:-8}"
# 200/301/302 = живой (главная редиректит на логин). Всё прочее (включая 000 = таймаут) = больной.
HEALTHY_CODES="${HEALTHY_CODES:-200 301 302}"

log() { logger -t hevvi-healthcheck "$*"; echo "$(date '+%F %T') $*"; }

cd "$DEPLOY_PATH" 2>/dev/null || { log "ERROR: cannot cd to $DEPLOY_PATH"; exit 1; }

code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time "$MAX_TIME" \
  -H "Host: ${CHECK_HOST_HEADER}" "$CHECK_URL" 2>/dev/null || echo "000")"

if [[ " $HEALTHY_CODES " == *" $code "* ]]; then
  # OK — тихо выходим, чтобы не спамить cron-почту.
  exit 0
fi

log "site unhealthy (http_code=$code) — restarting php nginx"
$COMPOSE restart php nginx >/dev/null 2>&1

sleep 5
code2="$(curl -sS -o /dev/null -w '%{http_code}' --max-time "$MAX_TIME" \
  -H "Host: ${CHECK_HOST_HEADER}" "$CHECK_URL" 2>/dev/null || echo "000")"

if [[ " $HEALTHY_CODES " == *" $code2 "* ]]; then
  log "recovered after restart (http_code=$code2)"
  exit 0
fi

log "STILL unhealthy after restart (http_code=$code2) — manual attention needed"
exit 1

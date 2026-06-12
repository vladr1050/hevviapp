# Устойчивость прода: почему сайт «висел» и как это предотвращено

## Что случилось (12.06.2026)

`www.hevvi.app` перестал отвечать. Диагностика показала:

- DNS, ping, порты 22/80/443 — **в порядке**;
- Caddy на `:80` отдавал `308` мгновенно, TLS на `:443` — валиден;
- но `curl http://127.0.0.1:8090/` (docker-nginx) **висел до таймаута**;
- контейнеры в `docker compose ps` — все **Up**.

Причина: **все воркеры PHP-FPM зависли**. Цепочка `Caddy → nginx(:8090) → php-fpm(:9000) → Symfony`
вставала на последнем участке. Docker не перезапускал контейнер, т.к. главный процесс
(`php-fpm`) был жив. В логах заранее был сигнал:

```
WARNING: [pool www] server reached pm.max_children setting (5)
```

Фон: до этого была нехватка памяти и hard reset (на сервере `Swap: 0`).

Лечение в моменте: `docker compose restart php nginx`.

---

## Что добавлено для предотвращения

| Файл | Что делает |
|------|-----------|
| `docker/php/zz-pool.conf` | Больше воркеров (5 → 10), `pm.max_requests=500` (рециклинг), `request_terminate_timeout=120s` (убивает зависший запрос до того, как он намертво займёт воркер). |
| `compose.prod.yaml` | Монтирует пул-конфиг, `restart: unless-stopped` для php/nginx/database, healthcheck на php (проверяет всю цепочку `nginx → php → Symfony`). |
| `scripts/hevvi-healthcheck.sh` | Cron на сервере: если локальный `:8090` не отвечает — сам перезапускает php+nginx. Главная сетка безопасности (plain Docker не рестартит контейнер по `unhealthy`). |

`zz-pool.conf` и компоуз-правки применяются **без ребилда образа** (конфиг монтируется
volume-ом, как `docker/php/uploads.ini`).

---

## Применение на сервере

### 1. Залить изменения и применить

```bash
cd /var/www/frpc_hevii-php-backoffice-service
git pull origin master

export COMPOSE_FILE=compose.yaml:compose.prod.yaml

# up -d пересоздаст php/nginx с новым mount, restart-политикой и healthcheck
docker compose up -d

# проверить, что пул применился (должно быть max_children=10)
docker compose exec -T php sh -c 'php-fpm -tt 2>&1 | grep -i "max_children\|max_requests\|terminate" || cat /usr/local/etc/php-fpm.d/zz-pool.conf'

# через ~1.5 мин (start_period) статус должен стать healthy
docker compose ps
```

### 2. Swap (обязательно при 4 GB RAM)

Без swap ядро при нехватке памяти убивает процессы (повтор инцидента):

```bash
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
free -h
```

### 3. Cron авто-восстановления

```bash
cp scripts/hevvi-healthcheck.sh /usr/local/bin/hevvi-healthcheck.sh
chmod +x /usr/local/bin/hevvi-healthcheck.sh
echo '*/5 * * * * root /usr/local/bin/hevvi-healthcheck.sh' > /etc/cron.d/hevvi-healthcheck

# проверка скрипта вручную (на здоровом сайте — тихий выход с кодом 0)
/usr/local/bin/hevvi-healthcheck.sh; echo "exit=$?"
```

---

## Если повторится — ручная диагностика

```bash
cd /var/www/frpc_hevii-php-backoffice-service
export COMPOSE_FILE=compose.yaml:compose.prod.yaml

curl -sS -o /dev/null -w "8090=%{http_code} time=%{time_total}s\n" --max-time 5 \
  -H "Host: www.hevvi.app" http://127.0.0.1:8090/

docker compose ps                          # health статусы
docker compose logs --tail=40 nginx        # ищем "upstream timed out"
docker compose exec php ps aux | grep fpm   # сколько воркеров, не зависли ли
free -h                                     # память/swap

# быстрый фикс
docker compose restart php nginx
```

---

## Параметры, которые можно крутить

- `pm.max_children` в `docker/php/zz-pool.conf` — при росте RAM сервера поднять (≈40–60 MB/воркер).
- `request_terminate_timeout=120s` — если есть легитимные длинные запросы (тяжёлые PDF),
  поднять; но держать **ниже** `fastcgi_read_timeout` (600s) в `nginx/default.conf`.
- Интервал cron `*/5` — частоту проверки.

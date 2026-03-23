# Деплой hevvi.app (Mac → сервер Hetzner)

Рабочий процесс, чтобы **не терять код**, **не затирать `.env`** и **всегда обновлять фронт**.

---

## Принципы

| Что | Зачем |
|-----|--------|
| **Код в Git** | Коммит + `push` в `master` — источник правды для продакшена. |
| **`.env` только на сервере** | В репозиторий не кладём; при `rsync` исключаем `.env`. |
| **`npm run build` на сервере** | После изменений в `assets/`, React, `package.json` — иначе UI «старый». |
| **Без `docker compose down -v`** | Том `database_data` сотрётся вместе с БД. |
| **Без `rsync --delete`** | На сервере могут лежать дампы в `docker/dumps/` — случайно не удалить. |

---

## Порядок (ручной)

### 1. Локально

```bash
cd frpc_hevii-php-backoffice-service
git status
git pull origin master
# правки → commit → push
git add -A && git commit -m "..." && git push origin master
```

Перед `pull`, если есть незакоммиченное: `git stash` → `pull` → `stash pop`.

### 2. На сервер (одним блоком по SSH)

Подставь свой хост и путь (у тебя: `root@37.27.188.238`, `/var/www/frpc_hevii-php-backoffice-service`).

```bash
export DEPLOY_HOST=root@37.27.188.238
export DEPLOY_PATH=/var/www/frpc_hevii-php-backoffice-service
export COMPOSE_FILE=compose.yaml:compose.prod.yaml

# С сервера: последний код из Git (альтернатива rsync с Mac)
ssh $DEPLOY_HOST "cd $DEPLOY_PATH && git pull origin master"
```

**Или с Mac — rsync** (как сейчас у вас):

```bash
cd /path/to/frpc_hevii-php-backoffice-service
rsync -az \
  --exclude='.git' --exclude='node_modules' \
  --exclude='var/cache/*' --exclude='var/log/*' \
  --exclude='.env' --exclude='.env.local' \
  ./ $DEPLOY_HOST:$DEPLOY_PATH/
```

### 3. Зависимости, фронт, миграции, кеш

```bash
ssh $DEPLOY_HOST "cd $DEPLOY_PATH && export COMPOSE_FILE=compose.yaml:compose.prod.yaml && \
  docker compose exec -T php composer install --no-dev --optimize-autoloader --no-interaction && \
  docker compose exec -T php sh -c 'cd /var/www/app && npm install && npm run build' && \
  docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction && \
  docker compose exec -T php php bin/console cache:clear --env=prod --no-warmup && \
  docker compose restart php nginx && \
  sleep 3 && \
  docker compose exec -T php php bin/console cache:warmup --env=prod"
```

### 4. Когда что запускать

| Менялось | Обязательно на сервере |
|----------|-------------------------|
| Только PHP/Twig/YAML переводы | `cache:clear` (+ при необходимости `restart php`) |
| `composer.lock` | `composer install --no-dev` |
| `assets/`, `package.json`, React | **`npm install && npm run build`** |
| `compose.yaml`, `nginx/`, `docker/php/` | `docker compose up -d --force-recreate` нужных сервисов |
| Новые миграции | `doctrine:migrations:migrate` |

---

## Скрипт с Mac

В репозитории: **`scripts/deploy-production.sh`** — выставь в начале файла `DEPLOY_HOST` и `DEPLOY_PATH`, сделай исполняемым: `chmod +x scripts/deploy-production.sh`, запуск: `./scripts/deploy-production.sh`.

Скрипт делает `rsync` + шаги из раздела 3.

---

## GitHub Actions (автодеплой с push в `master`)

Настроен workflow **`.github/workflows/deploy-production.yml`**. Инструкция по секретам: **[GITHUB_ACTIONS_DEPLOY.md](./GITHUB_ACTIONS_DEPLOY.md)**.

---

## Опционально: деплой только через Git на сервере

Если на сервере клонирован репозиторий и настроен **deploy key**:

1. `ssh сервер` → `cd $DEPLOY_PATH && git pull`
2. Те же команды `docker compose exec` (composer, npm build, migrate, cache).

Тогда с Mac не нужен `rsync`, но на сервере должен быть доступ к GitHub и аккуратно настроен `.env` (не в git).

---

## Чеклист после деплоя

- [ ] https://hevvi.app открывается  
- [ ] Жёсткое обновление страницы (Cmd+Shift+R), если менялся фронт  
- [ ] `/admin` логин  
- [ ] При смене API — проверить критичные сценарии (заказ, файлы)

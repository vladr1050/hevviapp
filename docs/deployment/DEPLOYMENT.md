# Развёртывание на внешнем сервере

Краткая инструкция по деплою Hevii BackOffice на VPS или другом сервере с Docker.

---

## Варианты

### 1. VPS с Docker (рекомендуется)

Подходит: **DigitalOcean**, **Hetzner**, **Selectel**, **AWS EC2**, любой VPS с Ubuntu 22.04+.

**Что нужно на сервере:**

- Docker и Docker Compose
- Домен (или IP) и при желании SSL (Let's Encrypt через Nginx/Caddy или Traefik)

**Шаги:**

1. Установить Docker и Docker Compose на сервер.
2. Клонировать репозиторий:  
   `git clone <repo-url> && cd frpc_hevii-php-backoffice-service`
3. Создать `.env` (скопировать из `.env` и задать production-значения, см. ниже).
4. Собрать и запустить в production-режиме (см. раздел «Production compose»).
5. Выполнить миграции и создать первого пользователя/админа.
6. Настроить Nginx/Caddy перед контейнерами как reverse proxy с SSL (опционально).

### 2. PaaS (Railway, Render, Fly.io и т.п.)

- **Railway** — можно развернуть через Dockerfile + добавить PostgreSQL (и при необходимости отдельно PostGIS).
- Настройка через веб-интерфейс: переменные окружения, домен, база.

Для деталей смотри документацию выбранной платформы (Docker + PostgreSQL).

---

## Переменные окружения для production

В `.env` или в настройках сервера/панели задайте:

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<сгенерировать: openssl rand -hex 32>

# База (на сервере — свои хост/пароль)
DATABASE_URL="postgresql://USER:PASSWORD@database:5432/DB?serverVersion=16&charset=utf8"

# JWT (ключи сгенерировать на сервере, см. ниже)
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=<ваш пароль для ключей>

# Mailjet (для писем)
MAILJET_API_KEY=...
MAILJET_API_SECRET=...
MAILJET_SENDER_EMAIL=...
MAILJET_SENDER_NAME=...
MAILJET_ENABLED=true
```

**JWT-ключи на сервере:**

```bash
# В контейнере или на хосте (если PHP установлен локально)
php bin/console lexik:jwt:generate-keypair --no-interaction
```

Пароль от ключей задайте в `JWT_PASSPHRASE` в `.env`.

---

## Production compose (пример)

Создайте на сервере файл `compose.prod.yaml` (рядом с `compose.yaml`) или переопределите переменные в `.env` и используйте один compose с профилем.

**Основные отличия от dev:**

- Сборка образа с `APP_MODE=prod` (в Dockerfile уже учтено: `npm run build`, composer без dev).
- Не монтировать исходный код в контейнер — только собранный образ.
- Задать `APP_DEBUG=0`, надёжный `APP_SECRET`, реальный `DATABASE_URL`.
- Пароль БД и секреты — только в `.env`, не коммитить.

Пример фрагмента для сервиса `php` в production:

```yaml
  php:
    build:
      context: .
      dockerfile: Dockerfile
      args:
        APP_MODE: prod
    environment:
      APP_ENV: prod
      APP_DEBUG: 0
      DATABASE_URL: ${DATABASE_URL}
      APP_SECRET: ${APP_SECRET}
      JWT_SECRET_KEY: /var/www/app/config/jwt/private.pem
      JWT_PUBLIC_KEY: /var/www/app/config/jwt/public.pem
      JWT_PASSPHRASE: ${JWT_PASSPHRASE}
      MAILJET_API_KEY: ${MAILJET_API_KEY}
      MAILJET_API_SECRET: ${MAILJET_API_SECRET}
      MAILJET_SENDER_EMAIL: ${MAILJET_SENDER_EMAIL}
      MAILJET_SENDER_NAME: ${MAILJET_SENDER_NAME}
      MAILJET_ENABLED: ${MAILJET_ENABLED:-true}
```

Без `volumes`, которые подключают локальный код (как в dev). База — отдельный сервис или внешний managed PostgreSQL.

---

## После первого запуска на сервере

1. **Миграции:**

   ```bash
   docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
   ```

2. **JWT-ключи** (если ещё не созданы):

   ```bash
   docker compose exec php php bin/console lexik:jwt:generate-keypair --no-interaction
   ```

3. **Первый пользователь (публичный логин):**

   ```bash
   docker compose exec php php bin/console app:user:create admin@yourdomain.com YourSecurePassword
   ```

4. **Первый менеджер (админка):**

   ```bash
   docker compose exec php php bin/console authorization:manager:create admin@yourdomain.com +371... First Last Password
   ```

5. **Кеш:**

   ```bash
   docker compose exec php php bin/console cache:clear --env=prod
   ```

---

## Сеть и порты

- Nginx в контейнере слушает порт 80 (или 8090 в dev).
- На сервере можно пробросить только 80/443 и перед контейнерами поставить свой Nginx/Caddy с SSL и proxy на контейнер с приложением.

---

## Резюме

| Шаг | Действие |
|-----|----------|
| 1 | Сервер с Docker + Docker Compose |
| 2 | Клонировать репо, настроить `.env` (prod) |
| 3 | Собрать образ с `APP_MODE=prod`, запустить compose |
| 4 | Сгенерировать JWT-ключи, выполнить миграции |
| 5 | Создать пользователя и менеджера, очистить кеш prod |
| 6 | При необходимости — reverse proxy и SSL |

Если напишешь, какой у тебя хостинг (свой VPS, Railway и т.д.), можно расписать шаги под него пошагово.

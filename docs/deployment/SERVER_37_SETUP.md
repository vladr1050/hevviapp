# Развёртывание на 37.27.188.238

Подключение к серверу — **с твоей машины** в терминале:

```bash
ssh root@37.27.188.238
# Введи пароль когда запросит
```

Дальше выполняй команды ниже по порядку.

---

## 1. Установка Docker и Docker Compose (если ещё нет)

```bash
apt-get update && apt-get install -y ca-certificates curl
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
apt-get update && apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
docker --version && docker compose version
```

---

## 2. Клонирование репозитория

```bash
cd /var/www  # или /opt, как удобнее
git clone https://github.com/praesyx/frpc_hevii-php-backoffice-service.git
cd frpc_hevii-php-backoffice-service
```

Если репо приватный — настрой SSH-ключ на сервере и клонируй по SSH-URL.

---

## 3. Файл .env для production

Создай `.env` (скопируй из примера и подставь свои значения):

```bash
cp .env .env.local
nano .env.local   # или vi
```

Задай минимум:

- `APP_ENV=prod`
- `APP_DEBUG=0`
- `APP_SECRET=` — сгенерируй: `openssl rand -hex 32`
- `DATABASE_URL=postgresql://app:СИЛЬНЫЙ_ПАРОЛЬ_БД@database:5432/app?serverVersion=16&charset=utf8`
- `POSTGRES_PASSWORD=СИЛЬНЫЙ_ПАРОЛЬ_БД` (тот же что в DATABASE_URL)
- JWT: пути к ключам и `JWT_PASSPHRASE=` (пароль для ключей)
- Mailjet (если нужна отправка писем)

Для Docker Compose на этом сервере оставь хост `database` в `DATABASE_URL` (имя сервиса).

---

## 4. Сборка и запуск (production)

Сборка образа с production-фронтом (нужен Dockerfile с `APP_MODE=prod`):

```bash
docker compose build --build-arg APP_MODE=prod
```

Перед первым запуском создай сеть (если в compose есть external):

```bash
docker network create shared-network 2>/dev/null || true
```

Запуск:

```bash
docker compose up -d
```

Проверка:

```bash
docker compose ps
docker compose logs -f php
```

---

## 5. JWT-ключи (если ещё не генерировали)

```bash
docker compose exec php php bin/console lexik:jwt:generate-keypair --no-interaction
```

Пароль для ключей задай в `JWT_PASSPHRASE` в `.env.local`.

---

## 6. Миграции БД

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

---

## 7. Первый пользователь и админ

Пользователь для сайта (публичный логин):

```bash
docker compose exec php php bin/console app:user:create admin@твой-домен.com Пароль
```

Менеджер для админки:

```bash
docker compose exec php php bin/console authorization:manager:create admin@твой-домен.com +370... Имя Фамилия Пароль
```

(Команда `app:user:create` есть только если в репо добавлен соответствующий Command.)

---

## 8. Кеш production

```bash
docker compose exec php php bin/console cache:clear --env=prod
```

---

## 9. Порты и доступ снаружи

По умолчанию приложение может слушать порт 8090 (или 80). Открой его в файрволе:

```bash
ufw allow 8090
ufw allow 22
ufw enable
```

Проверка в браузере: `http://37.27.188.238:8090`

Для HTTPS позже можно поставить Nginx/Caddy перед контейнерами и выдать сертификат (Let's Encrypt).

---

## Важно по безопасности

- Пароль root и пароли из `.env` никому не отправляй и не коммить в репо.
- Лучше создать отдельного пользователя с sudo и отключить вход root по паролю, оставив только вход по SSH-ключу.
- `.env.local` не должен попадать в git (должен быть в `.gitignore`).

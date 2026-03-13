# Домен hevvi.app

Инструкция по привязке домена **hevvi.app** к бэк-офису на сервере 37.27.188.238 с HTTPS (Let's Encrypt).

---

## 1. DNS

В панели регистратора домена создай A-записи:

| Тип | Имя  | Значение        | TTL  |
|-----|------|-----------------|------|
| A   | @    | 37.27.188.238   | 300  |
| A   | www  | 37.27.188.238   | 300  |

После сохранения подожди 5–30 минут (распространение DNS). Проверка:

```bash
dig +short hevvi.app
dig +short www.hevvi.app
```

Должен быть ответ: `37.27.188.238`.

---

## 2. Обратный прокси и HTTPS на сервере (Caddy)

Caddy сам получает сертификаты Let's Encrypt и проксирует трафик на контейнер.

На сервере:

```bash
ssh root@37.27.188.238
apt-get update && apt-get install -y debian-keyring debian-archive-keyring curl
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
apt-get update && apt-get install -y caddy
```

Создай конфиг Caddy:

```bash
cat > /etc/caddy/Caddyfile << 'EOF'
hevvi.app, www.hevvi.app {
    reverse_proxy 127.0.0.1:8090
}
EOF
```

Перезапусти Caddy и открой порты:

```bash
systemctl enable caddy && systemctl restart caddy
ufw allow 80/tcp && ufw allow 443/tcp && ufw reload
```

Проверка: открой в браузере https://hevvi.app — должен открыться бэк-офис с зелёным замком.

---

## 3. Приложение: DEFAULT_URI и trusted hosts

Чтобы ссылки в письмах и редиректы вели на домен, на сервере в `.env` задай:

```bash
# На сервере
cd /var/www/frpc_hevii-php-backoffice-service
sed -i 's|DEFAULT_URI=.*|DEFAULT_URI=https://hevvi.app|' .env
```

В проекте уже настроено:

- `config/packages/framework.yaml`: для prod включён `cookie_secure: true` (куки только по HTTPS).
- `config/packages/framework.yaml`: для prod заданы `trusted_hosts` (hevvi.app, www.hevvi.app).

После смены `.env` перезапусти PHP и почисти кеш:

```bash
docker compose -f compose.yaml -f compose.prod.yaml exec php php bin/console cache:clear --env=prod
docker compose -f compose.yaml -f compose.prod.yaml restart php
```

---

## 4. Итог

| URL | Назначение |
|-----|------------|
| https://hevvi.app | Бэк-офис (логин, сайт) |
| https://hevvi.app/admin | Админ-панель |
| http://37.27.188.238:8090 | Продолжит работать, но для продакшена используй домен с HTTPS. |

При необходимости можно закрыть прямой доступ по IP:8090 в UFW и оставить только 80/443 для Caddy.

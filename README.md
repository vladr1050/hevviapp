# Hevii BackOffice Service

> **Система управления бэк-офисом на Symfony 8 + PostgreSQL с PostGIS**

[![Symfony](https://img.shields.io/badge/Symfony-8.0-black.svg)](https://symfony.com)
[![PHP](https://img.shields.io/badge/PHP-8.4-blue.svg)](https://php.net)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-blue.svg)](https://postgresql.org)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)](LICENSE)

---

## 📋 Описание

Административная панель для управления заказами, зонами обслуживания и геоданными. Включает работу с картами, автоматическое отслеживание изменений и продвинутую систему фильтров.

---

## 🚀 Быстрый старт

### Требования

- Docker & Docker Compose
- Git
- 2 ГБ свободного места

### Установка за 5 минут

```bash
# 1. Клонируйте репозиторий (если еще не клонировали)
git clone <repository-url>
cd php-backoffice-service

# 2. Запустите контейнеры
docker compose up -d

# 3. Дождитесь готовности (обычно 30-60 секунд)
# Контейнер автоматически:
# - Установит PostGIS расширения
# - Создаст все таблицы
# - Загрузит геоданные (если есть дампы)

# 4. Проверьте работу
docker compose ps
```

**Готово!** 🎉

- **Админ-панель:** http://localhost:8090/admin
- **База данных:** `localhost:5432` (app/!ChangeMe!)

---

## 📦 Что внутри?

### Основные функции

| Модуль                   | Описание                                                            |
| ------------------------ | ------------------------------------------------------------------- |
| **GeoArea**              | Парсинг и управление геоданными (страны/города) через GADM          |
| **ServiceArea**          | Зоны обслуживания с матрицей цен по километражу                     |
| **Orders**               | Управление заказами с историей изменений статусов                   |
| **Order Attachments**    | PDF-вложения к заказам: загрузка, gzip-сжатие, раздача по salt      |
| **Map**                  | Интеграция карт Leaflet с поддержкой полигонов                      |

### Технологии

- **Backend:** Symfony 8.0, PHP 8.4, Doctrine ORM
- **Admin:** Sonata Admin 4.4
- **Database:** PostgreSQL 16 + PostGIS 3.4
- **Frontend:** Stimulus JS, Sass, Webpack Encore
- **Maps:** Leaflet.js
- **GeoData:** GADM 4.1

---

## 📖 Документация

### Для начинающих

📘 **[Руководство по установке](docs/installation/DOCKER_SETUP.md)** - Подробная пошаговая инструкция

### Основные разделы

- 📚 **[Функции проекта](docs/features/README.md)** - Описание всех возможностей
- 🏛️ **[Архитектура](docs/architecture/README.md)** - Структура и принципы проектирования
- 🔧 **[Устранение неполадок](docs/troubleshooting/README.md)** - Решение типичных проблем

---

## 🔧 Основные команды

### Docker

```bash
# Запуск
docker compose up -d

# Остановка
docker compose down

# Просмотр логов
docker compose logs -f php

# Перезапуск
docker compose restart
```

### База данных

```bash
# Подключение к PostgreSQL
docker compose exec database psql -U app -d app

# Создание миграции
docker compose exec php php bin/console make:migration

# Применение миграций
docker compose exec php php bin/console doctrine:migrations:migrate
```

### Геоданные

```bash
# Загрузка геоданных для Латвии (РЕКОМЕНДУЕТСЯ)
docker compose exec php php bin/console app:parse-geo-areas-gadm latvia

# Проверка данных
docker compose exec database psql -U app -d app -c "SELECT name FROM geo_area WHERE scope = 2;"
```

### Frontend

```bash
# Сборка assets (development)
docker compose exec php npm run dev

# Watch mode (автопересборка)
docker compose exec php npm run watch

# Production build
docker compose exec php npm run build
```

---

## 📎 PDF-вложения к заказам

> Функция позволяет прикреплять PDF-документы к заказам: через форму на `/user/requests` (React UI) и через вкладку «Файлы» в Sonata Admin.

### Как устроено

```
Загрузка → gzip (уровень 6) → public/uploads/orders/{salt}.pdf.gz
Раздача  → GET /files/{salt}       (JWT, для User/Carrier)
           GET /admin/files/{salt}  (session, для Manager/Admin)
```

- Путь до файла **никогда не передаётся** клиенту — только 64-символьный hex-salt
- Файлы сжимаются автоматически при сохранении (экономия места ~20–40% для PDF)
- При удалении заказа все вложения удаляются каскадно (ON DELETE CASCADE)

### Требования при развёртывании

#### 1. Применить миграцию БД

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

Создаёт таблицу `order_attachment` с уникальным индексом по `salt` и FK → `order`.

#### 2. Убедиться в правах на директорию загрузок

Директория создаётся автоматически при первой загрузке, но в production-окружении проверьте права:

```bash
# Создать директорию и выдать права веб-серверу
mkdir -p public/uploads/orders
chown -R www-data:www-data public/uploads
chmod -R 755 public/uploads
```

В Docker это уже настроено в `Dockerfile`:
```dockerfile
RUN mkdir -p var/cache var/log public/build && \
    chown -R www-data:www-data var public
```

#### 3. Лимиты загрузки — NGINX

В файле `nginx/default.conf` уже выставлены:

```nginx
client_max_body_size 30m;   # должен быть >= PHP post_max_size
fastcgi_buffers      16 16k;
fastcgi_buffer_size  32k;
```

После изменения конфига — перезагрузить NGINX **без перезапуска** контейнера:

```bash
docker compose exec nginx nginx -s reload
```

#### 4. Лимиты загрузки — PHP

Файл `docker/php/uploads.ini` монтируется в PHP-контейнер через `compose.yaml`:

```ini
upload_max_filesize = 25M
post_max_size       = 30M
```

Цепочка лимитов (`<=`): `upload_max_filesize ≤ post_max_size ≤ client_max_body_size`

При изменении значений пересоздайте PHP-контейнер:

```bash
docker compose up -d php
# Проверка:
docker compose exec php php -i | grep -E "upload_max|post_max"
```

#### 5. Исключить загруженные файлы из Git

В `.gitignore` должна быть строка (или директория уже исключена):

```gitignore
/public/uploads/
```

#### 6. Резервное копирование

Директория `public/uploads/orders/` содержит **пользовательские данные** — включите её в backup-стратегию наравне с базой данных.

---

## 🐛 Частые проблемы

### Контейнеры не запускаются

```bash
# Проверьте логи
docker compose logs

# Пересоздайте контейнеры
docker compose down
docker compose up -d --build
```

### База данных пустая

```bash
# Проверьте наличие PostGIS
docker compose exec database psql -U app -d app -c "SELECT PostGIS_version();"

# Пересоздайте схему
docker compose exec php php bin/console doctrine:schema:update --force
```

### Assets не собираются

```bash
# Пересоберите контейнер PHP
docker compose up -d --build php

# Проверьте наличие node_modules
docker compose exec php ls -la node_modules/

# Установите зависимости вручную
docker compose exec php npm install
docker compose exec php npm run dev

# npm error code ENOTEMPTY npm error syscall rename
docker compose exec php sh -lc "rm -rf node_modules package-lock.json && npm install --no-audit --no-fund --legacy-peer-deps"
```

### Файлы не загружаются — «Failed to upload files»

Типичные причины и решения:

| Симптом | Причина | Решение |
|---------|---------|---------|
| `HTTP 413` | `client_max_body_size` в NGINX меньше размера файла | Увеличить `client_max_body_size` в `nginx/default.conf`, затем `nginx -s reload` |
| `HTTP 413` | `post_max_size` или `upload_max_filesize` в PHP меньше файла | Увеличить значения в `docker/php/uploads.ini`, пересоздать контейнер |
| `HTTP 422` «Only PDF files allowed» | Загружается не PDF-файл | Убедиться, что файл — PDF (`application/pdf`) |
| `HTTP 422` «No files provided» | Поле формы называется не `files[]` | Проверить JS: `formData.append('files[]', file)` |
| `HTTP 500` | Нет прав на запись в `public/uploads/orders/` | `chown -R www-data:www-data public/uploads` |
| HTML-ответ вместо JSON | Symfony exception в dev-режиме | Смотреть логи: `docker compose logs -f php` |

```bash
# Проверить текущие PHP-лимиты
docker compose exec php php -i | grep -E "upload_max|post_max"

# Проверить NGINX-лимиты
docker compose exec nginx nginx -T | grep client_max

# Проверить права на директорию
docker compose exec php ls -la public/uploads/
```

### Подробнее

См. **[Troubleshooting](docs/troubleshooting/README.md)** для детального решения проблем.

---

## 🎯 Структура проекта

```
php-backoffice-service/
├── assets/              # Frontend код (JS, SCSS)
│   ├── controllers/     # Stimulus контроллеры
│   └── styles/          # SCSS стили
├── config/              # Конфигурация Symfony
│   ├── packages/        # Настройки bundles
│   └── routes/          # Маршруты
├── docker/              # Docker конфигурация
│   ├── db/init/         # SQL скрипты инициализации
│   └── dumps/           # Дампы данных
├── docs/                # 📚 ДОКУМЕНТАЦИЯ
│   ├── architecture/    # Архитектура проекта
│   ├── features/        # Описание функций
│   ├── installation/    # Инструкции по установке
│   └── troubleshooting/ # Решение проблем
├── migrations/          # Миграции БД
├── nginx/               # Конфигурация Nginx
├── public/              # Публичная директория
├── src/                 # PHP код приложения
│   ├── Admin/           # Sonata Admin классы
│   ├── Command/         # Console команды
│   ├── Entity/          # Doctrine сущности
│   ├── EventSubscriber/ # Event subscribers
│   ├── Service/         # Бизнес-логика
│   └── Repository/      # Репозитории
├── templates/           # Twig шаблоны
├── translations/        # Переводы (ru/en)
├── compose.yaml         # Docker Compose конфигурация
├── Dockerfile           # PHP-FPM образ
└── README.md            # 📖 Этот файл
```

---

## 👥 Разработка

### Соглашения о коде

- Следуем **PSR-12** стандарту
- Применяем принципы **SOLID** и **OOP**
- Используем **PHP 8.4** features (атрибуты, типизация)
- Документируем публичные методы

### Миграции

```bash
# Создание новой миграции
docker compose exec php php bin/console make:migration

# Применение миграций
docker compose exec php php bin/console doctrine:migrations:migrate

# Откат последней миграции
docker compose exec php php bin/console doctrine:migrations:migrate prev
```

### Очистка кеша

```bash
# Полная очистка
docker compose exec php php bin/console cache:clear

# Только prod кеш
docker compose exec php php bin/console cache:clear --env=prod
```

---

## 📄 Лицензия

**Proprietary** - SIA SLYFOX © 2026

Все права защищены. Код является собственностью компании.

---

## 🤝 Поддержка

При возникновении проблем:

1. Проверьте **[Troubleshooting](docs/troubleshooting/README.md)**
2. Изучите логи: `docker compose logs -f`
3. Обратитесь к ведущему разработчику

---

**Версия:** 2.1.0  
**Последнее обновление:** Март 2026  
**Статус:** ✅ Production Ready

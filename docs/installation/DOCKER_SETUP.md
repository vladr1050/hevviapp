# 🐳 Установка проекта через Docker

> **Пошаговая инструкция для новичков**

---

## 📋 Что потребуется

### Обязательно

- **Docker Desktop** (или Docker Engine + Docker Compose)
  - [Скачать для Windows](https://docs.docker.com/desktop/install/windows-install/)
  - [Скачать для macOS](https://docs.docker.com/desktop/install/mac-install/)
  - [Установка для Linux](https://docs.docker.com/engine/install/)
- **Git** - [Скачать](https://git-scm.com/downloads)
- **2 ГБ** свободного места на диске
- **Порты свободны:** 8090 (Nginx), 5432 (PostgreSQL)

### Опционально

- Любой текстовый редактор (VS Code, PHPStorm, Sublime)
- Клиент для PostgreSQL (DBeaver, pgAdmin)

---

## 🚀 Шаг 1: Подготовка

### 1.1. Проверьте установку Docker

Откройте терминал (cmd/PowerShell на Windows, Terminal на macOS/Linux) и выполните:

```bash
docker --version
docker compose version
```

**Ожидаемый результат:**
```
Docker version 24.x.x
Docker Compose version v2.x.x
```

Если команды не найдены - установите Docker Desktop.

### 1.2. Клонируйте репозиторий

```bash
# Перейдите в нужную папку
cd ~/Projects  # или C:\Projects на Windows

# Клонируйте проект (замените URL на ваш)
git clone <repository-url> php-backoffice-service
cd php-backoffice-service
```

---

## 🔧 Шаг 2: Запуск контейнеров

### 2.1. Запустите Docker Compose

```bash
docker compose up -d
```

**Что происходит:**
- `-d` = detached mode (фоновый режим)
- Docker скачивает образы (первый раз ~5-10 минут)
- Создаются контейнеры: `php`, `database`, `nginx`
- PHP контейнер собирает assets (~30-60 секунд)

**Ожидаемый вывод:**
```
[+] Running 4/4
 ✔ Network php-backoffice-service_hevii     Created
 ✔ Container php-backoffice-service-database-1  Started
 ✔ Container php-backoffice-service-php-1       Started
 ✔ Container php-backoffice-service-nginx-1     Started
```

### 2.2. Проверьте статус контейнеров

```bash
docker compose ps
```

**Должно быть:**
```
NAME                                  STATUS
php-backoffice-service-database-1     Up (healthy)
php-backoffice-service-nginx-1        Up
php-backoffice-service-php-1          Up
```

Если статус не `Up` - смотрите логи:

```bash
docker compose logs php
docker compose logs database
```

---

## 📦 Шаг 3: Инициализация базы данных

### 3.1. Дождитесь автоматической инициализации

Контейнер PHP автоматически выполняет при первом запуске:

1. ✅ Установка PostGIS расширений
2. ✅ Создание схемы БД (`doctrine:schema:update`)
3. ✅ Загрузка дампов геоданных (если есть в `docker/dumps/geo_areas/`)

**Проверьте логи:**

```bash
docker compose logs php | grep "✅"
```

**Должно быть:**
```
✅ PostGIS ready
✅ GeoArea dumps loaded successfully
```

### 3.2. Проверьте базу данных

```bash
# Подключитесь к PostgreSQL
docker compose exec database psql -U app -d app

# Выполните в psql:
\dt  # Список таблиц

# Должны быть таблицы:
# - geo_area
# - service_area
# - matrix_item
# - orders
# - order_history
# - carrier
# - manager
# - user

# Выйдите из psql
\q
```

### 3.3. (Опционально) Загрузите геоданные вручную

Если дампы не загрузились автоматически:

```bash
# Для Латвии (РЕКОМЕНДУЕТСЯ - использует GADM)
docker compose exec php php bin/console app:parse-geo-areas-gadm latvia

# Проверка
docker compose exec database psql -U app -d app -c "SELECT COUNT(*) FROM geo_area;"
# Должно быть: 8 (1 страна + 7 городов)
```

---

## 🌐 Шаг 4: Первый запуск

### 4.1. Откройте админ-панель

Откройте браузер и перейдите:

```
http://localhost:8090/admin
```

**Что вы увидите:**
- Форма входа (если настроена аутентификация)
- Или главную страницу админки с меню слева

### 4.2. Проверьте разделы

Слева в меню должны быть:
- 📦 **Заказы** (Orders)
- 🗺️ **Географические зоны** (Geo Areas)
- 🚚 **Зоны обслуживания** (Service Areas)
- 👥 **Пользователи** (Users / Carriers / Managers)

---

## 🎨 Шаг 5: Frontend Assets

### 5.1. Проверьте сборку

Assets собираются автоматически при сборке контейнера.

**Проверьте наличие файлов:**

```bash
docker compose exec php ls -la public/build/
```

**Должны быть:**
- `app.css`
- `app.js`
- `runtime.js`
- `manifest.json`

### 5.2. (Опционально) Пересборка в dev режиме

Если хотите разрабатывать frontend:

```bash
# Пересборка один раз
docker compose exec php npm run dev

# Автоматическая пересборка при изменениях
docker compose exec php npm run watch
```

---

## ✅ Шаг 6: Проверка работоспособности

### Чек-лист для проверки

- [ ] Контейнеры запущены: `docker compose ps` → все `Up`
- [ ] База создана: `docker compose exec database psql -U app -d app -c "\dt"` → список таблиц
- [ ] Геоданные загружены: `docker compose exec database psql -U app -d app -c "SELECT COUNT(*) FROM geo_area;"` → > 0
- [ ] Админка открывается: http://localhost:8090/admin
- [ ] Assets собраны: `docker compose exec php ls public/build/` → файлы присутствуют
- [ ] Нет ошибок в логах: `docker compose logs php | grep ERROR` → пусто

### Тестовые данные

Если хотите тестовые данные:

```bash
# Создайте зону обслуживания через админку
# Или загрузите тестовый дамп (если есть):
cat docker/dumps/test_data.sql | docker compose exec -T database psql -U app -d app
```

---

## 🔧 Дополнительные команды

### Управление контейнерами

```bash
# Остановить все
docker compose down

# Остановить и удалить volumes (⚠️ УДАЛИТ ДАННЫЕ БД)
docker compose down -v

# Перезапустить конкретный контейнер
docker compose restart php

# Пересобрать и запустить
docker compose up -d --build
```

### Работа с PHP контейнером

```bash
# Зайти в контейнер
docker compose exec php bash

# Внутри контейнера можете выполнять:
php bin/console cache:clear
composer install
npm run dev

# Выход: exit
```

### Просмотр логов

```bash
# Все логи
docker compose logs

# Только PHP
docker compose logs -f php

# Только база
docker compose logs -f database

# Последние 100 строк
docker compose logs --tail=100 php
```

### Очистка кеша Symfony

```bash
docker compose exec php php bin/console cache:clear
```

---

## 🐛 Частые проблемы при установке

### Проблема: "Port is already allocated"

**Причина:** Порт 8090 или 5432 уже занят.

**Решение:**

```bash
# Найдите процесс на порту (Linux/macOS)
lsof -i :8090
lsof -i :5432

# Или измените порты в compose.yaml:
# nginx:
#   ports:
#     - "8091:80"  # Вместо 8090
# database:
#   ports:
#     - "5433:5432"  # Вместо 5432
```

### Проблема: "No such image"

**Причина:** Docker не может скачать образ.

**Решение:**

```bash
# Проверьте интернет соединение
ping google.com

# Очистите Docker кеш
docker system prune -a

# Попробуйте снова
docker compose up -d --build
```

### Проблема: "database connection refused"

**Причина:** База еще не запустилась.

**Решение:**

```bash
# Подождите 30 секунд и проверьте
docker compose logs database | grep "ready to accept connections"

# Должно быть два раза (master + standby)
# Перезапустите PHP если нужно:
docker compose restart php
```

### Проблема: "Permission denied" (Linux)

**Причина:** Недостаточно прав для Docker.

**Решение:**

```bash
# Добавьте себя в группу docker
sudo usermod -aG docker $USER

# Выйдите и войдите заново
# Или перезагрузите систему
```

### Проблема: Страница не открывается

**Проверьте:**

1. Nginx запущен: `docker compose ps nginx`
2. Порт открыт: `curl http://localhost:8090`
3. Файлы есть: `docker compose exec php ls public/`

**Логи Nginx:**

```bash
docker compose exec nginx cat /var/log/nginx/error.log
```

---

## 📚 Что дальше?

После успешной установки:

1. **Изучите функции:** [Features Documentation](../features/README.md)
2. **Поймите архитектуру:** [Architecture Guide](../architecture/README.md)
3. **Начните разработку:** Создайте новую ветку и начинайте кодить!

---

## 🆘 Нужна помощь?

1. Проверьте **[Troubleshooting](../troubleshooting/README.md)**
2. Изучите логи: `docker compose logs -f`
3. Спросите в команде

---

**Версия:** 1.0  
**Последнее обновление:** Февраль 2026

# 🔧 Troubleshooting - Решение проблем

> **Руководство по устранению типичных проблем**

---

## 📋 Содержание

- [Docker](#docker)
- [База данных](#база-данных)
- [PostGIS](#postgis)
- [Frontend / Assets](#frontend--assets)
- [Геоданные](#геоданные)
- [Sonata Admin](#sonata-admin)
- [Производительность](#производительность)

---

## 🐳 Docker

### Контейнеры не запускаются

**Проблема:** `docker compose up -d` завершается с ошибкой

**Решение:**

```bash
# 1. Проверьте логи
docker compose logs

# 2. Проверьте занятые порты
lsof -i :8090  # macOS/Linux
lsof -i :5432

# 3. Измените порты в compose.yaml если заняты
# nginx: "8091:80"
# database: "5433:5432"

# 4. Пересоздайте контейнеры
docker compose down
docker compose up -d --build
```

### Ошибка "Port is already allocated"

**Проблема:** Порт уже занят другим приложением

**Решение:**

```bash
# Найдите процесс на порту
lsof -i :8090

# Остановите процесс
kill -9 <PID>

# Или измените порт в compose.yaml
```

### Контейнер постоянно перезапускается

**Проблема:** Контейнер в статусе "Restarting"

**Решение:**

```bash
# Проверьте логи
docker compose logs -f php

# Часто это:
# - Ошибка в entrypoint.sh
# - Неправильная конфигурация
# - Нехватка памяти

# Зайдите в контейнер и проверьте вручную
docker compose run --rm php bash
php bin/console --version
```

---

## 💾 База данных

### База данных пустая

**Проблема:** Таблицы не создаются автоматически

**Решение:**

```bash
# 1. Проверьте подключение к БД
docker compose exec database psql -U app -d app

# 2. Если подключение работает, создайте схему вручную
docker compose exec php php bin/console doctrine:schema:update --force

# 3. Проверьте наличие таблиц
docker compose exec database psql -U app -d app -c "\dt"
```

### "Connection refused" к PostgreSQL

**Проблема:** PHP не может подключиться к базе

**Решение:**

```bash
# 1. Проверьте, что база запущена
docker compose ps database

# 2. Проверьте health check
docker compose logs database | grep "ready to accept connections"

# 3. Подождите 30-60 секунд после запуска

# 4. Проверьте DATABASE_URL в .env
# Должен быть: postgresql://app:!ChangeMe!@database:5432/app
```

### Миграции не применяются

**Проблема:** `doctrine:migrations:migrate` ничего не делает

**Решение:**

```bash
# 1. Проверьте статус миграций
docker compose exec php php bin/console doctrine:migrations:status

# 2. Если "0 New Migrations" - все применено

# 3. Если есть новые - примените
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# 4. Если ошибка - проверьте SQL в файлах миграций
```

---

## 🗺️ PostGIS

### PostGIS расширение не установлено

**Проблема:** `ERROR: type "geometry" does not exist`

**Решение:**

```bash
# 1. Проверьте наличие PostGIS
docker compose exec database psql -U app -d app -c "SELECT PostGIS_version();"

# 2. Если ошибка - установите вручную
docker compose exec database psql -U app -d app -c "CREATE EXTENSION IF NOT EXISTS postgis;"

# 3. Проверьте снова
docker compose exec database psql -U app -d app -c "SELECT PostGIS_version();"
# Должно вернуть версию: 3.4
```

### Геометрия невалидна

**Проблема:** `ERROR: Invalid geometry`

**Решение:**

```sql
-- Проверьте валидность всех геометрий
SELECT id, name, ST_IsValid(geometry) as is_valid
FROM geo_area
WHERE NOT ST_IsValid(geometry);

-- Исправьте невалидную геометрию
UPDATE geo_area
SET geometry = ST_Buffer(geometry, 0)
WHERE NOT ST_IsValid(geometry);

-- Проверьте снова
SELECT COUNT(*) FROM geo_area WHERE NOT ST_IsValid(geometry);
-- Должно быть: 0
```

### Точка не содержится в полигоне

**Проблема:** `ST_Contains` возвращает `false` хотя должно быть `true`

**Решение:**

```sql
-- 1. Проверьте SRID (должен быть 4326)
SELECT ST_SRID(geometry) FROM geo_area WHERE id = 'uuid';

-- 2. Если не 4326 - исправьте
UPDATE geo_area SET geometry = ST_SetSRID(geometry, 4326);

-- 3. Проверьте порядок координат (lng, lat)
SELECT ST_AsText(ST_SetSRID(ST_MakePoint(24.1052, 56.9496), 4326));
-- Должно быть: POINT(24.1052 56.9496)

-- 4. Проверьте тип геометрии
SELECT ST_GeometryType(geometry) FROM geo_area WHERE id = 'uuid';
-- Должно быть: ST_Polygon или ST_MultiPolygon
```

---

## 🎨 Frontend / Assets

### Assets не собираются

**Проблема:** `public/build/` пустая или нет файлов

**Решение:**

```bash
# 1. Проверьте наличие node_modules
docker compose exec php ls -la node_modules/

# 2. Если нет - установите
docker compose exec php npm install

# 3. Соберите assets
docker compose exec php npm run dev

# 4. Проверьте результат
docker compose exec php ls -la public/build/
# Должны быть: app.css, app.js, manifest.json
```

### "Module not found" ошибка

**Проблема:** Webpack не может найти модуль

**Решение:**

```bash
# 1. Очистите node_modules и package-lock.json
docker compose exec php rm -rf node_modules package-lock.json

# 2. Переустановите зависимости
docker compose exec php npm install

# 3. Соберите снова
docker compose exec php npm run dev
```

### Изменения в JS/CSS не применяются

**Проблема:** Изменения в коде не видны в браузере

**Решение:**

```bash
# 1. Используйте watch mode для автоматической пересборки
docker compose exec php npm run watch

# 2. Или очистите кеш Symfony
docker compose exec php php bin/console cache:clear

# 3. Очистите кеш браузера (Ctrl+Shift+R)

# 4. Проверьте время модификации файлов
docker compose exec php ls -la public/build/
```

### Карта не отображается

**Проблема:** Leaflet карта не показывается на странице

**Решение:**

1. Откройте консоль браузера (F12)
2. Проверьте ошибки JavaScript
3. Проверьте загрузку Leaflet CSS:

```html
<!-- Должно быть в <head> -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
```

4. Проверьте инициализацию карты:

```javascript
// В консоли браузера
console.log(window.L);  // Должен быть объект Leaflet
```

---

## 🗺️ Геоданные

### Геоданные не загружаются

**Проблема:** `geo_area` таблица пустая

**Решение:**

```bash
# 1. Проверьте наличие дампов
ls -la docker/dumps/geo_areas/

# 2. Если дампы есть - загрузите вручную
cat docker/dumps/geo_areas/geo_areas_dump_lva_01.sql \
  | docker compose exec -T database psql -U app -d app

# 3. Если дампов нет - запустите парсер
docker compose exec php php bin/console app:parse-geo-areas-gadm latvia

# 4. Проверьте результат
docker compose exec database psql -U app -d app -c "SELECT COUNT(*) FROM geo_area;"
```

### GADM парсер падает с ошибкой

**Проблема:** Команда `app:parse-geo-areas-gadm` завершается с ошибкой

**Возможные причины и решения:**

**1. Не может скачать данные:**

```bash
# Проверьте интернет соединение
docker compose exec php curl -I https://geodata.ucdavis.edu/

# Если доступа нет - используйте предзагруженные дампы
```

**2. Не может распаковать ZIP:**

```bash
# Проверьте наличие ZipArchive
docker compose exec php php -m | grep zip

# Если нет - пересоберите контейнер
docker compose up -d --build php
```

**3. Невалидный JSON:**

```bash
# Попробуйте другую страну
docker compose exec php php bin/console app:parse-geo-areas-gadm estonia
```

### Overpass API таймауты (504)

**Проблема:** `app:parse-geo-areas` падает с 504 Gateway Timeout

**Решение:**

✅ **Используйте GADM вместо Overpass API!**

```bash
docker compose exec php php bin/console app:parse-geo-areas-gadm latvia
```

GADM не имеет таймаутов и работает быстрее.

---

## 🎛️ Sonata Admin

### Админка не открывается

**Проблема:** 404 или 500 ошибка на `/admin`

**Решение:**

```bash
# 1. Очистите кеш
docker compose exec php php bin/console cache:clear

# 2. Проверьте маршруты
docker compose exec php php bin/console debug:router | grep admin

# 3. Проверьте конфигурацию Sonata
docker compose exec php php bin/console debug:config sonata_admin

# 4. Проверьте логи
docker compose logs -f php | grep ERROR
```

### Форма не сохраняется

**Проблема:** При нажатии "Save" ничего не происходит

**Решение:**

1. Откройте консоль браузера (F12)
2. Проверьте ошибки JavaScript
3. Проверьте логи PHP:

```bash
docker compose logs -f php
```

4. Проверьте валидацию в Entity:

```php
use Symfony\Component\Validator\Constraints as Assert;

class ServiceArea
{
    #[Assert\NotBlank]
    private string $name;
}
```

### Коллекция (матрица) не работает

**Проблема:** Не добавляются/удаляются элементы в CollectionType

**Решение:**

Проверьте настройки в Admin классе:

```php
->add('matrixItems', CollectionType::class, [
    'by_reference' => false,  // ✅ ОБЯЗАТЕЛЬНО!
    'type_options' => [
        'delete' => true,      // ✅ Разрешить удаление
    ],
], [
    'edit' => 'inline',
    'inline' => 'table',
])
```

---

## ⚡ Производительность

### Медленные запросы

**Проблема:** Страницы загружаются долго

**Решение:**

```bash
# 1. Включите Symfony Profiler
# В .env: APP_ENV=dev

# 2. Откройте страницу с /_profiler
# Проверьте раздел "Database"

# 3. Найдите медленные запросы и добавьте индексы
```

**Пример:**

```sql
-- Медленный запрос
SELECT * FROM orders WHERE status = 3;

-- Добавьте индекс
CREATE INDEX idx_orders_status ON orders(status);

-- Проверьте улучшение
EXPLAIN ANALYZE SELECT * FROM orders WHERE status = 3;
```

### N+1 проблема

**Проблема:** Слишком много запросов к БД

**Решение:**

```php
// ❌ Плохо (N+1)
$orders = $orderRepository->findAll();
foreach ($orders as $order) {
    echo $order->getUser()->getName();  // +N запросов
}

// ✅ Хорошо (1 запрос)
$orders = $orderRepository->createQueryBuilder('o')
    ->leftJoin('o.user', 'u')
    ->addSelect('u')
    ->getQuery()
    ->getResult();
```

### Нехватка памяти

**Проблема:** `Allowed memory size exhausted`

**Решение:**

```bash
# 1. Увеличьте лимит в php.ini
echo "memory_limit = 512M" | docker compose exec -T php tee -a /usr/local/etc/php/conf.d/php.ini

# 2. Перезапустите контейнер
docker compose restart php

# 3. Используйте batch обработку
$batchSize = 100;
$i = 0;
foreach ($orders as $order) {
    // обработка
    if (($i % $batchSize) === 0) {
        $em->flush();
        $em->clear();
    }
    $i++;
}
```

---

## 🆘 Дополнительная помощь

### Логи

```bash
# Symfony логи
docker compose exec php tail -f var/log/dev.log

# PHP-FPM логи
docker compose logs -f php

# Nginx логи
docker compose logs -f nginx

# PostgreSQL логи
docker compose logs -f database
```

### Debugging

```bash
# Включите debug режим
# .env: APP_DEBUG=1

# Проверьте конфигурацию
docker compose exec php php bin/console debug:config

# Проверьте контейнер сервисов
docker compose exec php php bin/console debug:container

# Проверьте события
docker compose exec php php bin/console debug:event-dispatcher
```

### Полная переустановка

Если ничего не помогает:

```bash
# 1. Остановите все
docker compose down -v  # ⚠️ УДАЛИТ ДАННЫЕ БД!

# 2. Удалите образы
docker rmi $(docker images -q php-backoffice-service*)

# 3. Очистите кеши
rm -rf var/cache/* var/log/*

# 4. Пересоберите с нуля
docker compose build --no-cache
docker compose up -d

# 5. Подождите инициализации (60 секунд)

# 6. Проверьте
docker compose ps
docker compose logs -f
```

---

**Версия:** 1.0  
**Последнее обновление:** Февраль 2026

**Если проблема не решена** - проверьте логи и обратитесь к ведущему разработчику.

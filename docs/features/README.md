# 📚 Функции проекта

> **Обзор всех возможностей системы**

---

## 🗺️ GeoArea - Географические зоны

### Описание

Система импорта и управления географическими данными (страны и города) с поддержкой полигонов и координат.

### Основные возможности

- ✅ **Импорт через GADM 4.1** - идеальные заполненные полигоны
- ✅ **Импорт через Overpass API** - альтернативный метод
- ✅ **PostGIS интеграция** - работа с геометрией в PostgreSQL
- ✅ **Визуализация на карте** - Leaflet.js для отображения
- ✅ **Иерархия зон** - страны и города (scope: COUNTRY/CITY)
- ✅ **ISO3 коды** - стандартизированные коды стран

### Команды

```bash
# GADM (рекомендуется) - лучшее качество
docker compose exec php php bin/console app:parse-geo-areas-gadm latvia

# Overpass API - альтернатива
docker compose exec php php bin/console app:parse-geo-areas latvia

# Тестирование подключения
docker compose exec php php bin/console app:test-osm-connection
```

### Структура данных

**Таблица:** `geo_area`

| Поле | Тип | Описание |
|------|-----|----------|
| id | UUID | Уникальный идентификатор |
| name | VARCHAR(255) | Название (Riga, Latvia) |
| scope | INT | Уровень (1=Country, 2=City) |
| country_iso3 | VARCHAR(3) | ISO3 код страны (LVA, EST, LTU) |
| geometry | GEOMETRY | PostGIS полигон |
| lat | DECIMAL | Широта центра (nullable) |
| lng | DECIMAL | Долгота центра (nullable) |

### Преимущества GADM

| Характеристика | Overpass API | GADM 4.1 |
|----------------|--------------|----------|
| Качество полигонов | ⚠️ Контуры | ✅ Заполненные |
| Содержит координаты | ❌ | ✅ |
| Скорость | 5-10 мин | 7 сек |
| Таймауты | Часто | Никогда |

### Подробнее

📖 **[GeoArea Parser Documentation](GEOAREA_PARSER.md)**

---

## 🚚 ServiceArea - Зоны обслуживания

### Описание

Управление зонами обслуживания с матрицей цен по километражу.

### Основные возможности

- ✅ **Управление зонами** - создание и редактирование зон обслуживания
- ✅ **Матрица цен** - гибкая система ценообразования
- ✅ **Диапазоны километража** - от-до с ценой
- ✅ **Каскадное удаление** - автоматическое удаление элементов матрицы
- ✅ **Встроенные формы** - удобное редактирование в Sonata Admin
- ✅ **Валидация** - проверка пересечений диапазонов

### Структура данных

**Таблица:** `service_area`

| Поле | Тип | Описание |
|------|-----|----------|
| id | UUID | Уникальный идентификатор |
| name | VARCHAR(255) | Название зоны (Рига, Московская обл.) |
| created_at | TIMESTAMP | Дата создания |
| updated_at | TIMESTAMP | Дата обновления |

**Таблица:** `matrix_item`

| Поле | Тип | Описание |
|------|-----|----------|
| id | UUID | Уникальный идентификатор |
| service_area_id | UUID FK | Связь с зоной обслуживания |
| mileage_from | INT | Километраж от |
| mileage_to | INT | Километраж до |
| price | INT | Цена (в центах) |
| created_at | TIMESTAMP | Дата создания |
| updated_at | TIMESTAMP | Дата обновления |

### Пример использования

```
Зона: Рига
Матрица цен:
  0-50 км    → 1000 центов (10.00 EUR)
  51-100 км  → 1500 центов (15.00 EUR)
  101-200 км → 2500 центов (25.00 EUR)
```

### Подробнее

📖 **[ServiceArea Documentation](SERVICE_AREA.md)**

---

## 📦 Orders - Управление заказами

### Описание

Полноценная система управления заказами с автоматическим отслеживанием изменений статусов.

### Основные возможности

- ✅ **Статусы заказов** - REQUEST, BILL, PAID, ASSIGNED, CANCELLED
- ✅ **История изменений** - автоматическое логирование всех изменений статуса
- ✅ **Определение инициатора** - кто изменил (User, Carrier, Manager, System)
- ✅ **Фильтрация** - по статусу, дате, инициатору
- ✅ **Автоназначение** - фильтры для перевозчиков
- ✅ **Метаданные** - старый и новый статус для каждого изменения

### Структура данных

**Таблица:** `orders`

| Поле | Тип | Описание |
|------|-----|----------|
| id | UUID | Уникальный идентификатор |
| status | INT | Статус (1=REQUEST, 2=BILL, 3=PAID, 4=ASSIGNED, 5=CANCELLED) |
| user_id | UUID FK | Пользователь |
| carrier_id | UUID FK | Перевозчик (nullable) |
| service_area_id | UUID FK | Зона обслуживания |
| price | INT | Цена (в центах) |
| created_at | TIMESTAMP | Дата создания |
| updated_at | TIMESTAMP | Дата обновления |

**Таблица:** `order_history`

| Поле | Тип | Описание |
|------|-----|----------|
| id | UUID | Уникальный идентификатор |
| order_id | UUID FK | Связь с заказом |
| status | INT | Новый статус |
| changed_by | INT | Тип изменившего (1=USER, 2=CARRIER, 3=SYSTEM, 4=MANUAL) |
| changed_at | TIMESTAMP | Дата изменения |

### Типы изменивших (changedBy)

| Константа | Значение | Описание |
|-----------|----------|----------|
| USER | 1 | Изменено пользователем (User) |
| CARRIER | 2 | Изменено перевозчиком (Carrier) |
| SYSTEM | 3 | Изменено системой (без авторизации) |
| MANUAL | 4 | Изменено менеджером (Manager) |

### Автоматическое отслеживание

Реализовано через **OrderHistorySubscriber** (Doctrine Event Subscriber):

1. Отслеживает изменения статуса в `preUpdate`
2. Определяет текущего пользователя через Symfony Security
3. Определяет тип изменившего (instanceof Manager/User/Carrier)
4. Создает запись в `order_history`
5. Сохраняет в `postFlush`

### Подробнее

📖 **[Order History Documentation](ORDER_HISTORY.md)**

---

## 📎 Order Attachments - PDF-вложения к заказам

### Описание

Система загрузки, хранения и раздачи PDF-документов, прикреплённых к заказам. Файлы автоматически сжимаются при сохранении, раздаются только по уникальному salt без раскрытия пути на диске.

### Основные возможности

- ✅ **Загрузка через React UI** — drag & drop или кнопка на `/user/requests`
- ✅ **Загрузка через Sonata Admin** — вкладка «Файлы» в форме редактирования заказа
- ✅ **gzip-сжатие** — уровень 6, экономия места ~20–40% для PDF
- ✅ **Безопасная раздача** — только по 64-символьному hex-salt, путь не раскрывается
- ✅ **Контроль доступа** — User видит только свои файлы, Carrier — только по назначенному заказу, Admin — всё
- ✅ **Каскадное удаление** — при удалении Order все вложения удаляются из БД и с диска
- ✅ **Множественная загрузка** — один запрос = несколько файлов (`files[]`)

### Структура данных

**Таблица:** `order_attachment`

| Поле | Тип | Описание |
|------|-----|----------|
| id | UUID | Уникальный идентификатор |
| salt | VARCHAR(128) | Уникальный hex-токен для раздачи (64 символа) |
| file_path | VARCHAR(512) | Путь относительно `public/` (`uploads/orders/{salt}.pdf.gz`) |
| original_name | VARCHAR(255) | Оригинальное имя файла от клиента |
| file_size | BIGINT | Размер **сжатого** файла на диске (байты) |
| related_order_id | UUID FK | Заказ (ON DELETE CASCADE) |
| created_at | TIMESTAMPTZ | Дата загрузки |
| updated_at | TIMESTAMPTZ | Дата обновления |

### API-эндпоинты

| Метод | URL | Авторизация | Описание |
|-------|-----|-------------|----------|
| `POST` | `/api/orders/{id}/attachments` | JWT (ROLE_USER) | Загрузить файлы (`multipart/form-data`, поле `files[]`) |
| `DELETE` | `/api/orders/{id}/attachments/{salt}` | JWT (ROLE_USER) | Удалить вложение |
| `GET` | `/files/{salt}` | JWT (IS_AUTHENTICATED_FULLY) | Скачать файл (User/Carrier) |
| `GET` | `/admin/files/{salt}` | Session (ROLE_ADMIN) | Скачать файл (Admin) |

### Пример загрузки через API

```javascript
// 1. Создать заказ
const { id } = await apiCreateOrder(token, payload)

// 2. Загрузить файлы
const formData = new FormData()
files.forEach(file => formData.append('files[]', file))

await fetch(`/api/orders/${id}/attachments`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}` },
    body: formData,  // Content-Type НЕ ставить вручную!
})
```

### Требования при развёртывании

> ⚠️ Подробная инструкция по развёртыванию — в главном [README.md](../../README.md#-pdf-вложения-к-заказам)

Кратко:
1. `docker compose exec php php bin/console doctrine:migrations:migrate` — создать таблицу
2. Проверить права: `chown -R www-data:www-data public/uploads`
3. NGINX: `client_max_body_size 30m` — уже в `nginx/default.conf`
4. PHP: `upload_max_filesize = 25M` — уже в `docker/php/uploads.ini`
5. Добавить `public/uploads/` в backup-стратегию

---

## 🗺️ Map Integration - Интеграция карт

### Описание

Система работы с картами на основе Leaflet.js с поддержкой полигонов, маркеров и интерактивного управления.

### Основные возможности

- ✅ **Leaflet.js** - мощная библиотека карт
- ✅ **Полигоны** - отображение GeoJSON данных
- ✅ **Интерактивность** - выбор областей, зум, перемещение
- ✅ **Select2 интеграция** - удобный выбор геозон
- ✅ **Auto-zoom** - автоматическое масштабирование на все слои
- ✅ **Read-only режим** - для просмотра (show view)
- ✅ **Edit режим** - для редактирования (edit view)

### Архитектура (SOLID)

```
Presentation Layer (Stimulus Controllers)
    ↓ uses
Service Layer (MapService, ApiService, GeoAreaService)
    ↓ uses
Infrastructure (Leaflet.js, Fetch API)
```

**Принципы:**
- ✅ Single Responsibility - каждый сервис решает одну задачу
- ✅ Open/Closed - легко расширяется
- ✅ Dependency Injection - конфигурация через конструктор
- ✅ Composition over Inheritance

### Компоненты

**Stimulus Controllers:**
- `geo_area_map_controller.js` - для edit view (с формой)
- `geo_area_view_map_controller.js` - для show view (только просмотр)

**Services:**
- `MapService` - управление картой (initialize, addLayer, removeLayer, fitToAll)
- `ApiService` - HTTP запросы (getCountries, getCities, getGeometry)
- `GeoAreaService` - состояние зон (addArea, removeArea, hasArea, getAll)

### Конфигурация

```yaml
# config/packages/map.yaml
parameters:
    map.nominatim_api_url: '%env(MAP_NOMINATIM_API_URL)%'
    map.default_latitude: '%env(float:MAP_DEFAULT_LATITUDE)%'
    map.default_longitude: '%env(float:MAP_DEFAULT_LONGITUDE)%'
    map.default_zoom: '%env(int:MAP_DEFAULT_ZOOM)%'
    map.user_agent: '%env(MAP_USER_AGENT)%'
```

### Подробнее

📖 **[Map Integration Documentation](MAP_INTEGRATION.md)**

---

## 🎨 Frontend

### Технологии

- **Stimulus** - модульная JS архитектура
- **Webpack Encore** - сборка assets
- **SASS** - препроцессор CSS
- **Leaflet.js** - карты
- **Select2** - продвинутые dropdown

### Структура

```
assets/
├── controllers/          # Stimulus контроллеры
│   ├── geo_area_map_controller.js
│   ├── geo_area_view_map_controller.js
│   └── ...
├── services/            # JS сервисы
│   ├── MapService.js
│   ├── ApiService.js
│   └── GeoAreaService.js
└── styles/              # SCSS стили
    ├── app.scss
    └── ...
```

### Команды

```bash
# Development build
docker compose exec php npm run dev

# Watch mode
docker compose exec php npm run watch

# Production build
docker compose exec php npm run build
```

---

## 👥 Пользователи и роли

### Типы пользователей

| Тип | Описание | Права |
|-----|----------|-------|
| **Manager** | Менеджер | Полный доступ ко всему |
| **User** | Клиент | Создание заказов, просмотр своих заказов |
| **Carrier** | Перевозчик | Просмотр назначенных заказов, изменение статуса |

### Аутентификация

Используется **FRPC Sonata Authorization** bundle для управления доступом.

---

## 🌐 Локализация

### Поддерживаемые языки

- 🇷🇺 Русский (ru)
- 🇬🇧 Английский (en)

### Файлы переводов

```
translations/
├── AppBundle.ru.yaml
├── AppBundle.en.yaml
├── validators.ru.yaml
└── validators.en.yaml
```

### Примеры переводов

```yaml
# AppBundle.ru.yaml
menu:
    geo_areas: "Географические зоны"
    service_areas: "Зоны обслуживания"
    orders: "Заказы"

form:
    label_name: "Название"
    label_status: "Статус"
```

---

## 🔔 Что дальше?

- **[Архитектура проекта](../architecture/README.md)** - Узнайте как устроен код
- **[Troubleshooting](../troubleshooting/README.md)** - Решение проблем
- **[Installation Guide](../installation/DOCKER_SETUP.md)** - Подробная установка

---

**Версия:** 1.0  
**Последнее обновление:** Февраль 2026

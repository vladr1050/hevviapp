# 🗺️ GeoArea Parser - Парсер геоданных

> **Система импорта географических данных для стран и городов**

---

## 📋 Обзор

GeoArea Parser - это набор команд для импорта географических данных (страны и города) в систему с сохранением полигонов и координат в PostgreSQL + PostGIS.

### Поддерживаемые источники

1. **GADM 4.1** (Global Administrative Areas) - **РЕКОМЕНДУЕТСЯ** ✅
2. **Overpass API** (OpenStreetMap) - альтернатива

---

## 🏆 GADM - Рекомендуемый метод

### Преимущества

| Характеристика | Значение |
|----------------|----------|
| **Качество полигонов** | ✅✅ Идеальные заполненные полигоны |
| **Координаты городов** | ✅✅ Все города содержат lat/lng |
| **Скорость импорта** | ✅✅ 7 секунд для страны |
| **Таймауты API** | ✅✅ Нет (локальная загрузка) |
| **Размер дампа** | 108 КБ для Латвии |
| **Валидность геометрии** | ✅✅ Изначально валидна |

### Использование

```bash
# Импорт для Латвии
docker compose exec php php bin/console app:parse-geo-areas-gadm latvia

# Импорт для Эстонии
docker compose exec php php bin/console app:parse-geo-areas-gadm estonia

# Импорт для Литвы
docker compose exec php php bin/console app:parse-geo-areas-gadm lithuania
```

### Что загружается

**Латвия (8 записей):**
- 1 страна: Latvia (64,711 км²)
- 7 городов: Riga, Daugavpils, Liepāja, Jelgava, Jūrmala, Ventspils, Rēzekne

**Эстония (5 записей):**
- 1 страна: Estonia
- 4 города: Tallinn, Tartu, Narva, Pärnu

**Литва (5 записей):**
- 1 страна: Lithuania
- 4 города: Vilnius, Kaunas, Klaipėda, Šiauliai

### Как это работает

1. **Скачивание данных:**
   - GADM предоставляет GeoJSON файлы по странам
   - Файлы скачиваются с UC Davis mirror
   - Формат: `gadm41_{ISO3}_1.json` (ADM1 уровень)

2. **Парсинг:**
   - Извлечение страны (ADM0)
   - Извлечение городов (ADM1 с типом "City")
   - Конвертация GeoJSON в WKT для PostGIS

3. **Сохранение:**
   - Страна: scope=COUNTRY (1), координаты центра
   - Города: scope=CITY (2), координаты + полигоны
   - Все полигоны заполненные и валидные

### Результат

```sql
SELECT 
    name, 
    scope,
    ST_NumGeometries(geometry) as parts,
    ROUND(ST_Area(geography::geography)/1000000, 3) as area_km2
FROM geo_area 
WHERE country_iso3 = 'LVA'
ORDER BY scope, name;
```

| name | scope | parts | area_km2 |
|------|-------|-------|----------|
| Latvia | 1 | 1 | 64711.234 |
| Daugavpils | 2 | 1 | 2.721 |
| Jelgava | 2 | 1 | 1.599 |
| Jūrmala | 2 | 1 | 3.369 |
| Liepāja | 2 | 1 | 3.710 |
| Rēzekne | 2 | 2 | 2.679 |
| Riga | 2 | 1 | 3.369 |
| Ventspils | 2 | 1 | 2.513 |

**100% успех!** ✨

---

## 🌍 Overpass API - Альтернативный метод

### Когда использовать

- Когда нужны данные стран, не поддерживаемых GADM
- Когда нужна максимальная детализация мелких объектов
- Для специфических запросов геоданных

### Использование

```bash
# Импорт для страны
docker compose exec php php bin/console app:parse-geo-areas latvia

# Тестирование подключения
docker compose exec php php bin/console app:test-osm-connection
```

### Ограничения

⚠️ **Проблемы:**
- Полигоны могут быть контурами (не заполненные)
- Города могут не содержать координаты центра
- Частые таймауты (504 Gateway Timeout)
- Медленный импорт (5-10 минут)
- Требует исправления геометрии (`ST_Buffer(geometry, 0)`)

### Как это работает

1. **Запрос к Overpass API:**
   - Поиск страны по названию
   - Поиск городов с `place=city`
   - Загрузка GeoJSON данных

2. **Парсинг:**
   - Извлечение relation для страны
   - Извлечение node/way для городов
   - Конвертация в WKT

3. **Исправление:**
   - Применение `ST_Buffer(geometry, 0)` для валидности
   - Извлечение центроидов если нет координат

---

## 🔧 Архитектура

### Классы и компоненты

```
src/Service/GeoArea/
├── Contract/
│   ├── GadmDataProviderInterface.php       # Интерфейс для GADM провайдеров
│   └── OsmDataProviderInterface.php        # Интерфейс для OSM провайдеров
├── GadmDataProvider/
│   └── GadmJsonProvider.php                # Загрузка GADM данных
├── OsmDataProvider/
│   └── OverpassProvider.php                # Загрузка OSM данных
├── GadmGeoAreaParser.php                   # Парсер GADM
└── OsmGeoAreaParser.php                    # Парсер OSM

src/Command/
├── ParseGeoAreasGadmCommand.php            # Команда для GADM
└── ParseGeoAreasCommand.php                # Команда для OSM
```

### SOLID принципы

✅ **S** - Отдельные классы для GADM и OSM  
✅ **O** - Легко добавить новые источники данных  
✅ **L** - Все парсеры реализуют общий интерфейс  
✅ **I** - Разные интерфейсы для разных провайдеров  
✅ **D** - Зависимости через интерфейсы  

---

## 💾 Структура данных

### Таблица: geo_area

```sql
CREATE TABLE geo_area (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    scope INT NOT NULL,  -- 1=COUNTRY, 2=CITY
    country_iso3 VARCHAR(3) NOT NULL,
    geometry GEOMETRY(Geometry, 4326) NOT NULL,
    lat NUMERIC(10, 8) NULL,
    lng NUMERIC(11, 8) NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Индексы для производительности
CREATE INDEX idx_geo_area_geometry ON geo_area USING GIST(geometry);
CREATE INDEX idx_geo_area_scope ON geo_area(scope);
CREATE INDEX idx_geo_area_country_iso3 ON geo_area(country_iso3);
```

### Константы Scope

```php
class GeoArea {
    const SCOPE_COUNTRY = 1;
    const SCOPE_CITY = 2;
}
```

---

## 🧪 Проверка данных

### Список загруженных зон

```bash
docker compose exec database psql -U app -d app -c "
SELECT name, scope, country_iso3 
FROM geo_area 
ORDER BY scope, name;
"
```

### Проверка геометрии

```bash
docker compose exec database psql -U app -d app -c "
SELECT 
    name,
    ST_GeometryType(geometry) as type,
    ST_NumGeometries(geometry) as parts,
    ST_IsValid(geometry) as is_valid
FROM geo_area;
"
```

### Проверка точки в полигоне

```bash
# Проверка: точка центра Риги (56.9496, 24.1052) в полигоне Риги
docker compose exec database psql -U app -d app -c "
SELECT 
    name,
    ST_Contains(
        geometry,
        ST_SetSRID(ST_MakePoint(24.1052, 56.9496), 4326)
    ) as contains_riga_center
FROM geo_area 
WHERE name = 'Riga';
"
# Должно вернуть: t (true)
```

### Экспорт в GeoJSON

```bash
docker compose exec database psql -U app -d app -c "
SELECT 
    jsonb_build_object(
        'type', 'Feature',
        'properties', jsonb_build_object('name', name),
        'geometry', ST_AsGeoJSON(geometry)::jsonb
    )
FROM geo_area 
WHERE name = 'Riga';
" > riga.geojson
```

Откройте `riga.geojson` на http://geojson.io для визуализации!

---

## 📦 Дампы базы данных

### Создание дампа

```bash
# Дамп всех геозон Латвии
docker compose exec database pg_dump -U app -d app \
  --table=geo_area \
  --data-only \
  --inserts \
  | grep "INSERT INTO geo_area" \
  > docker/dumps/geo_areas/geo_areas_dump_lva_$(date +%Y%m%d).sql
```

### Загрузка дампа

```bash
# Очистка таблицы
docker compose exec database psql -U app -d app -c "TRUNCATE TABLE geo_area CASCADE;"

# Загрузка дампа
cat docker/dumps/geo_areas/geo_areas_dump_lva_01.sql \
  | docker compose exec -T database psql -U app -d app

# Проверка
docker compose exec database psql -U app -d app -c "SELECT COUNT(*) FROM geo_area;"
```

### Автоматическая загрузка

Дампы из `docker/dumps/geo_areas/geo_areas_dump_*.sql` загружаются автоматически при первом запуске контейнера (см. `entrypoint.sh`).

---

## 🎯 Использование в коде

### Получение всех стран

```php
$countries = $geoAreaRepository->findBy(['scope' => GeoArea::SCOPE_COUNTRY]);
```

### Получение городов страны

```php
$cities = $geoAreaRepository->findBy([
    'scope' => GeoArea::SCOPE_CITY,
    'countryISO3' => 'LVA'
]);
```

### Проверка точки в зоне

```php
// Находится ли точка (lat, lng) в какой-либо зоне?
$qb = $em->createQueryBuilder();
$qb->select('g')
   ->from(GeoArea::class, 'g')
   ->where('ST_Contains(g.geometry, ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)) = true')
   ->setParameter('lat', 56.9496)
   ->setParameter('lng', 24.1052);

$geoArea = $qb->getQuery()->getOneOrNullResult();
```

---

## 🚀 Лучшие практики

### Используйте GADM для:

✅ Административных границ (страны, регионы, города)  
✅ Зон обслуживания  
✅ ServiceArea в приложении  
✅ Быстрого импорта данных  
✅ Продакшн окружения  

### Используйте Overpass API для:

- Специфических объектов (парки, больницы, школы)
- Максимальной детализации
- Нестандартных запросов
- Стран не поддерживаемых GADM

### Оптимизация

- Используйте GIST индексы для геометрии
- Кешируйте запросы к геозонам
- Предзагружайте данные через дампы
- Используйте партиционирование для больших объемов

---

## 📚 Дополнительные ресурсы

### Источники данных

- **GADM:** https://gadm.org/
- **OpenStreetMap:** https://www.openstreetmap.org/
- **Overpass API:** https://overpass-api.de/
- **Natural Earth:** https://www.naturalearthdata.com/

### Документация

- **PostGIS:** https://postgis.net/docs/
- **Doctrine PostGIS:** https://github.com/jsor/doctrine-postgis
- **GeoJSON:** https://geojson.org/

---

**Версия:** 1.0  
**Последнее обновление:** Февраль 2026  
**Статус:** ✅ Production Ready

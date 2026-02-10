# GeoArea Parser Service

Сервис для парсинга геоданных из OpenStreetMap для сущности `GeoArea`.

## 📦 Структура пакета

```
Service/GeoArea/
├── Contract/                          # Интерфейсы (Dependency Inversion)
│   ├── OsmDataProviderInterface.php  # Получение данных из OSM
│   ├── GeometryParserInterface.php   # Парсинг геометрии
│   └── GeoAreaDataDumperInterface.php # Генерация дампа
│
├── DTO/                               # Data Transfer Objects
│   ├── CountryConfigDto.php          # Конфигурация страны
│   └── OsmAreaDto.php                # DTO области
│
├── OsmDataProvider/                   # Реализации провайдеров данных
│   └── OverpassApiClient.php         # Клиент Overpass API
│
├── GeometryParser/                    # Реализации парсеров геометрии
│   └── OsmGeometryParser.php         # GeoJSON → WKT MULTIPOLYGON
│
├── DataDumper/                        # Реализации генераторов дампа
│   └── GeoAreaSqlDumper.php          # SQL дамп
│
├── Config/                            # Конфигурация
│   └── CountryConfigProvider.php     # Конфигурации стран
│
├── GeoAreaParser.php                  # Главный координатор
└── README.md                          # Этот файл
```

## 🎯 Принципы SOLID

Каждый компонент следует принципам SOLID:

- **S** (Single Responsibility): Каждый класс - одна ответственность
- **O** (Open/Closed): Легко расширяется без изменения кода
- **L** (Liskov Substitution): Реализации интерфейсов взаимозаменяемы
- **I** (Interface Segregation): Маленькие специализированные интерфейсы
- **D** (Dependency Inversion): Зависимости через интерфейсы

## 🚀 Использование

### Основное использование через Symfony Command

```bash
php bin/console app:parse-geo-areas latvia
```

### Программное использование

```php
use App\Service\GeoArea\GeoAreaParser;
use App\Service\GeoArea\Config\CountryConfigProvider;

// Через DI контейнер
$parser = $container->get(GeoAreaParser::class);
$configProvider = $container->get(CountryConfigProvider::class);

// Получить конфигурацию Латвии
$latviaConfig = $configProvider->getCountryConfig('latvia');

// Парсить страну
$areas = $parser->parseCountry($latviaConfig);

// Или парсить и сразу создать дамп
$parser->parseAndDump([$latviaConfig], '/path/to/dump.sql');
```

## 🔧 Расширение функциональности

### Добавление нового источника данных

Создайте класс, реализующий `OsmDataProviderInterface`:

```php
namespace App\Service\GeoArea\OsmDataProvider;

use App\Service\GeoArea\Contract\OsmDataProviderInterface;

class YourCustomProvider implements OsmDataProviderInterface
{
    public function getCountryBoundary(string $relationId): array
    {
        // Ваша реализация
    }

    public function getCitiesInCountry(string $countryRelationId, int $adminLevel = 8): array
    {
        // Ваша реализация
    }
}
```

Зарегистрируйте в `config/services.yaml`:

```yaml
App\Service\GeoArea\Contract\OsmDataProviderInterface:
    class: App\Service\GeoArea\OsmDataProvider\YourCustomProvider
```

### Добавление нового формата вывода

Создайте класс, реализующий `GeoAreaDataDumperInterface`:

```php
namespace App\Service\GeoArea\DataDumper;

use App\Service\GeoArea\Contract\GeoAreaDataDumperInterface;

class YourCustomDumper implements GeoAreaDataDumperInterface
{
    public function generateSqlDump(array $areas, string $outputPath): void
    {
        // Ваша реализация (например, JSON, XML, fixtures)
    }
}
```

### Добавление новой страны

Отредактируйте `Config/CountryConfigProvider.php`:

```php
private const COUNTRIES = [
    'your_country' => [
        'name' => 'Your Country',
        'iso3' => 'XXX',
        'osmRelationId' => '123456',
        'adminLevelCity' => 8,
    ],
];
```

## 📊 Пример данных

### Входные данные (GeoJSON из OSM)

```json
{
  "type": "Feature",
  "properties": {
    "name": "Latvia",
    "name:en": "Latvia",
    "admin_level": "2"
  },
  "geometry": {
    "type": "MultiPolygon",
    "coordinates": [[[[27.0, 57.0], ...]]]
  }
}
```

### Выходные данные (SQL)

```sql
INSERT INTO geo_area (id, name, scope, geometry, country_iso3, created_at, updated_at) 
VALUES (
    'uuid-here',
    'Latvia',
    1,
    ST_GeomFromText('MULTIPOLYGON(...)', 4326),
    'LVA',
    '2026-02-06 12:00:00',
    '2026-02-06 12:00:00'
);
```

## 🧪 Тестирование

```bash
# Проверка подключения к OSM API
php bin/console app:test-osm-connection
```

## 📚 Дополнительная документация

- [Быстрый старт](../../../GEO_AREA_QUICKSTART.md)
- [Полная документация](../../../GEO_AREA_PARSER_README.md)
- [Резюме реализации](../../../GEO_AREA_IMPLEMENTATION_SUMMARY.md)

## 🔗 Полезные ссылки

- [OpenStreetMap](https://www.openstreetmap.org/)
- [Overpass API](https://overpass-api.de/)
- [Overpass Turbo](https://overpass-turbo.eu/) - тестирование запросов
- [PostGIS Documentation](https://postgis.net/docs/)

## ⚙️ Технические требования

- PHP >= 8.4
- Symfony 8.0
- PostgreSQL с PostGIS
- curl extension
- Доступ к интернету (для Overpass API)

## 📝 Лицензия

SIA SLYFOX Confidential - Copyright (C) 2026

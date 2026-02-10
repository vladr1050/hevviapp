# GeoArea Dumps Directory

Эта директория содержит SQL дампы для сущности GeoArea, сгенерированные парсером OSM данных.

## 📁 Структура файлов

```
geo_areas/
├── geo_areas_dump_lva_01.sql    # Латвия, часть 1
├── geo_areas_dump_lva_02.sql    # Латвия, часть 2
├── geo_areas_dump_lva_03.sql    # Латвия, часть 3
├── geo_areas_dump_est_01.sql    # Эстония, часть 1
└── ...
```

## 🚀 Генерация дампов

```bash
php bin/console app:parse-geo-areas latvia
```

Дампы автоматически сохраняются в эту директорию.

## 📖 Документация

См. [GEO_AREA_DUMP_FILES_INFO.md](../../../GEO_AREA_DUMP_FILES_INFO.md) для подробной информации.

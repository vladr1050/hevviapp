# Database Dumps Directory

Эта директория содержит SQL дампы для различных сущностей, организованные по поддиректориям.

## 📁 Структура

```
dumps/
├── geo_areas/           # Дампы для GeoArea (страны и города из OSM)
│   ├── README.md
│   ├── .gitignore
│   └── *.sql            # Сгенерированные дампы (игнорируются git)
│
└── [future_entity]/     # Будущие дампы для других сущностей
```

## 🎯 Назначение

Эта структура поддерживает чистоту проекта:
- ✅ Все дампы в одном месте
- ✅ Легко найти и управлять
- ✅ Каждая сущность в своей поддиректории
- ✅ Автоматическая загрузка при старте контейнера

## 🚀 Использование

### Генерация дампов

```bash
# GeoArea
php bin/console app:parse-geo-areas latvia
```

### Ручная загрузка

```bash
# Загрузить все дампы GeoArea
for file in docker/dumps/geo_areas/*.sql; do
    psql "$DATABASE_URL" -f "$file"
done
```

### Очистка

```bash
# Удалить все дампы GeoArea
rm -f docker/dumps/geo_areas/*.sql

# Удалить все дампы
find docker/dumps -name "*.sql" -type f -delete
```

## 📝 Добавление новых дампов

Если вам нужно добавить дампы для новой сущности:

1. Создайте поддиректорию: `docker/dumps/your_entity/`
2. Добавьте `.gitignore` с содержимым:
   ```
   *.sql
   !.gitignore
   ```
3. Создайте `README.md` с описанием
4. Обновите `entrypoint.sh` для автоматической загрузки

## 🔒 Git

Все `.sql` файлы автоматически игнорируются git через `.gitignore` в каждой поддиректории.

Структура директорий сохраняется с помощью `.gitkeep` и `README.md` файлов.

## 📚 Документация

- [GeoArea Dumps Info](../../GEO_AREA_DUMP_FILES_INFO.md)
- [GeoArea Quick Start](../../GEO_AREA_QUICKSTART.md)

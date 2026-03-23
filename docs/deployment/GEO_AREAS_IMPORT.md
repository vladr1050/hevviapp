# Импорт GeoArea (страны и города) для админки

Селект **Country** на вкладке **Geo Areas** в Service Area заполняется из таблицы `geo_area` (записи со `scope = 1` — страна).  
Если импорт не выполнялся, API `GET /api/geo-area/countries` вернёт `[]` — в выпадающем списке будет пусто / «No results found».

## Вариант 1: GADM (рекомендуется, SQL-дамп)

На сервере (или локально, затем загрузить SQL в БД):

```bash
cd /var/www/frpc_hevii-php-backoffice-service
COMPOSE_FILE=compose.yaml:compose.prod.yaml docker compose exec -T php \
  php bin/console app:parse-geo-areas-gadm latvia -o /tmp/latvia_geo.sql
```

Команда интерактивная (подтверждение). Для неинтерактивного запуска можно передать `yes`:

```bash
echo yes | docker compose exec -T php php bin/console app:parse-geo-areas-gadm latvia -o /tmp/latvia_geo.sql
```

Загрузка в PostgreSQL:

```bash
docker compose exec -T database psql -U app -d app < /tmp/latvia_geo.sql
```

(Путь к файлу внутри контейнера `php` может отличаться — при необходимости скопируй дамп на хост и подставь в `psql`.)

## Вариант 2: OSM-парсер

```bash
docker compose exec php php bin/console app:parse-geo-areas latvia
```

См. справку команды и `docs/installation/DOCKER_SETUP.md` / `docs/CUSTOM_GEO_AREAS.md`.

## После импорта

Очистить кеш prod при необходимости:

```bash
docker compose exec php php bin/console cache:clear --env=prod
```

Проверка: в браузере открыть `https://ваш-домен/api/geo-area/countries` — должен быть JSON-массив с объектами вроде `{ "id": "...", "name": "Latvia", "countryISO3": "LVA" }`.

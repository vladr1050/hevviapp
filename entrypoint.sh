#!/bin/bash
set -e

echo "🚀 Starting Hevii php-backoffice-service..."
echo "📝 APP_ENV: ${APP_ENV:-not set}"
echo "🐛 APP_DEBUG: ${APP_DEBUG:-not set}"

# Проверяем, что необходимые директории существуют
if [ ! -d "var" ]; then
    mkdir -p var/cache var/log
fi

if [ ! -d "public" ]; then
    mkdir -p public
fi

# Меняем права только на папки, где это нужно (если они существуют)
if [ -d "var" ]; then
    chown -R www-data:www-data var
fi

if [ -d "public" ]; then
    chown -R www-data:www-data public
fi

# Проверяем и устанавливаем PostGIS (если еще не установлен)
echo "🗺️  Checking PostGIS extension..."
psql "$DATABASE_URL" -c "CREATE EXTENSION IF NOT EXISTS postgis; CREATE EXTENSION IF NOT EXISTS postgis_topology;" 2>/dev/null || {
    echo "⚠️  Could not install PostGIS via psql, trying alternative method..."
    php bin/console dbal:run-sql "CREATE EXTENSION IF NOT EXISTS postgis; CREATE EXTENSION IF NOT EXISTS postgis_topology;" 2>/dev/null || true
}
echo "✅ PostGIS ready"

# Схему на prod меняют только миграции. doctrine:schema:update --force на каждом старте
# ломает объекты вне ORM (например PostgreSQL SEQUENCE order_number_seq для order_number).
if [ "${APP_ENV:-dev}" = "dev" ]; then
    echo "🛠️  APP_ENV=dev: doctrine:schema:update --force"
    php bin/console doctrine:schema:update --force || true
else
    echo "ℹ️  APP_ENV=${APP_ENV:-}: пропуск schema:update (используйте doctrine:migrations:migrate)"
fi

# Загрузка GeoArea дампов (если существуют)
# Каталог проекта = каталог этого скрипта (в Docker: /var/www/app, см. Dockerfile WORKDIR)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")" && pwd)"
GEO_AREAS_DUMP_DIR="${GEO_AREAS_DUMP_DIR:-$SCRIPT_DIR/docker/dumps/geo_areas}"
GEO_AREAS_DUMP_PATTERN="geo_areas_dump_*_*.sql"

# Находим все файлы дампа
DUMP_FILES=$(find "$GEO_AREAS_DUMP_DIR" -maxdepth 1 -name "$GEO_AREAS_DUMP_PATTERN" -type f 2>/dev/null | sort)

if [ -n "$DUMP_FILES" ]; then
    echo "📍 Found GeoArea dump files..."
    
    # Проверяем, есть ли уже данные в таблице geo_area
    EXISTING_COUNT=$(php bin/console dbal:run-sql "SELECT COUNT(*) FROM geo_area" --quiet 2>/dev/null | tail -n 1 || echo "0")
    
    if [ "$EXISTING_COUNT" = "0" ] || [ -z "$EXISTING_COUNT" ]; then
        echo "   Loading geo areas from dump files..."
        
        FILE_COUNT=0
        for DUMP_FILE in $DUMP_FILES; do
            FILE_COUNT=$((FILE_COUNT + 1))
            FILENAME=$(basename "$DUMP_FILE")
            echo "   📄 Loading part $FILE_COUNT: $FILENAME"
            
            psql "$DATABASE_URL" -f "$DUMP_FILE" 2>/dev/null || {
                echo "   ⚠️  Failed to load via psql, trying alternative method..."
                php bin/console dbal:run-sql "$(cat $DUMP_FILE)" 2>/dev/null || true
            }
        done
        
        echo "✅ GeoArea dumps loaded successfully ($FILE_COUNT files)"
    else
        echo "ℹ️  GeoArea table already contains data ($EXISTING_COUNT records), skipping dump load"
    fi
else
    echo "ℹ️  No GeoArea dump files found in $GEO_AREAS_DUMP_DIR, skipping"
fi

# Запускаем PHP-FPM
exec php-fpm -F

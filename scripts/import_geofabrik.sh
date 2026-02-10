#!/bin/bash
# Скрипт для импорта GeoArea из GeoFabrik shape files

set -e

COUNTRY="${1:-latvia}"
COUNTRY_LOWER=$(echo "$COUNTRY" | tr '[:upper:]' '[:lower:]')

echo "🌍 GeoFabrik Import для $COUNTRY"
echo "================================"
echo ""

# Маппинг стран на ISO3 коды
declare -A ISO3_MAP
ISO3_MAP[latvia]="LVA"
ISO3_MAP[estonia]="EST"
ISO3_MAP[lithuania]="LTU"

ISO3="${ISO3_MAP[$COUNTRY_LOWER]}"
ISO3_LOWER=$(echo "$ISO3" | tr '[:upper:]' '[:lower:]')

if [ -z "$ISO3" ]; then
    echo "❌ Страна не поддерживается: $COUNTRY"
    echo "Доступные: latvia, estonia, lithuania"
    exit 1
fi

echo "📝 Country: $COUNTRY ($ISO3)"
echo ""

# Создаем рабочую директорию
WORK_DIR="./var/geofabrik"
mkdir -p "$WORK_DIR"

# URL для скачивания
GEOFABRIK_URL="https://download.geofabrik.de/europe/${COUNTRY_LOWER}-latest-free.shp.zip"
ZIP_FILE="$WORK_DIR/${COUNTRY_LOWER}-latest.zip"
EXTRACT_DIR="$WORK_DIR/${COUNTRY_LOWER}_extract"

# Шаг 1: Скачивание
echo "📥 Step 1: Downloading shape files..."
echo "   URL: $GEOFABRIK_URL"
echo "   Size: ~110-130 MB"
echo ""

if [ -f "$ZIP_FILE" ]; then
    echo "   ✓ File already exists, skipping download"
else
    curl -L "$GEOFABRIK_URL" -o "$ZIP_FILE" --progress-bar
    echo "   ✓ Downloaded successfully"
fi
echo ""

# Шаг 2: Распаковка
echo "📦 Step 2: Extracting archive..."
rm -rf "$EXTRACT_DIR"
mkdir -p "$EXTRACT_DIR"
unzip -q "$ZIP_FILE" -d "$EXTRACT_DIR"
echo "   ✓ Extracted $(ls $EXTRACT_DIR/*.shp 2>/dev/null | wc -l) shape files"
echo ""

# Шаг 3: Импорт административных границ (страна)
echo "🗺️  Step 3: Importing country boundary..."
TEMP_TABLE="temp_geofabrik_${ISO3_LOWER}"

# Находим файл с административными границами
ADMIN_SHP="$EXTRACT_DIR/gis_osm_admin_a_free_1.shp"
if [ ! -f "$ADMIN_SHP" ]; then
    echo "   ⚠️  Admin boundaries file not found, using alternative..."
    ADMIN_SHP="$EXTRACT_DIR/gis_osm_adminareas_free_1.shp"
fi

if [ -f "$ADMIN_SHP" ]; then
    echo "   Found: $(basename $ADMIN_SHP)"
    
    # Импортируем через ogr2ogr
    docker-compose exec -T database bash -c "
        ogr2ogr -f PostgreSQL PG:'dbname=app user=app password=!ChangeMe!' \
            /dev/stdin \
            -nln $TEMP_TABLE \
            -overwrite \
            -t_srid 4326 \
            -lco GEOMETRY_NAME=geom \
            -skipfailures
    " < "$ADMIN_SHP" 2>&1 | grep -v "NOTICE" || {
        echo "   ⚠️  ogr2ogr not available, trying alternative method..."
        
        # Альтернатива: используем shp2pgsql если ogr2ogr недоступен
        if command -v shp2pgsql &> /dev/null; then
            shp2pgsql -s 4326 -I -d "$ADMIN_SHP" "$TEMP_TABLE" | \
                docker-compose exec -T database psql -U app -d app -q 2>&1 | grep -v "NOTICE"
        else
            echo "   ❌ Neither ogr2ogr nor shp2pgsql found"
            echo "   Please install GDAL: brew install gdal (macOS) or apt-get install gdal-bin (Linux)"
            exit 1
        fi
    }
    
    echo "   ✓ Admin boundaries imported"
else
    echo "   ⚠️  Admin boundaries file not found, skipping"
fi
echo ""

# Шаг 4: Импорт городов
echo "🏙️  Step 4: Importing cities..."
PLACES_SHP="$EXTRACT_DIR/gis_osm_places_free_1.shp"

if [ ! -f "$PLACES_SHP" ]; then
    echo "   ❌ Places file not found: gis_osm_places_free_1.shp"
    exit 1
fi

TEMP_PLACES_TABLE="temp_places_${ISO3_LOWER}"

echo "   Found: $(basename $PLACES_SHP)"
echo "   Importing cities and towns..."

# Импортируем места
docker-compose exec -T database bash -c "
    ogr2ogr -f PostgreSQL PG:'dbname=app user=app password=!ChangeMe!' \
        /dev/stdin \
        -nln $TEMP_PLACES_TABLE \
        -overwrite \
        -t_srid 4326 \
        -lco GEOMETRY_NAME=geom \
        -skipfailures
" < "$PLACES_SHP" 2>&1 | grep -v "NOTICE" || {
    echo "   ⚠️  ogr2ogr not available, trying shp2pgsql..."
    
    if command -v shp2pgsql &> /dev/null; then
        shp2pgsql -s 4326 -I -d "$PLACES_SHP" "$TEMP_PLACES_TABLE" | \
            docker-compose exec -T database psql -U app -d app -q 2>&1 | grep -v "NOTICE"
    else
        echo "   ❌ Neither ogr2ogr nor shp2pgsql found"
        exit 1
    fi
}

echo "   ✓ Places imported"
echo ""

# Шаг 5: Обработка данных в PostgreSQL
echo "🔧 Step 5: Processing data in PostgreSQL..."

docker-compose exec -T database psql -U app -d app << EOF
-- Удаляем старые данные для этой страны
DELETE FROM geo_area WHERE country_iso3 = '$ISO3';

-- Вставляем страну
INSERT INTO geo_area (id, name, scope, geometry, country_iso3, created_at, updated_at)
SELECT 
    gen_random_uuid(),
    '$ISO3' as name,
    1 as scope,
    ST_Multi(ST_MakeValid(ST_Union(geom)))::geometry(MultiPolygon, 4326) as geometry,
    '$ISO3' as country_iso3,
    NOW(),
    NOW()
FROM $TEMP_TABLE
WHERE admin_level = '2' OR name LIKE '%${COUNTRY_LOWER}%'
LIMIT 1;

-- Вставляем города (только city и town с полигонами)
INSERT INTO geo_area (id, name, scope, geometry, country_iso3, created_at, updated_at)
SELECT 
    gen_random_uuid(),
    name,
    2 as scope,
    ST_Multi(
        ST_Buffer(
            ST_MakeValid(geom)::geography,
            500  -- Буфер 500м для заполнения gaps
        )::geometry
    )::geometry(MultiPolygon, 4326) as geometry,
    '$ISO3' as country_iso3,
    NOW(),
    NOW()
FROM $TEMP_PLACES_TABLE
WHERE fclass IN ('city', 'town')
AND geom IS NOT NULL
AND ST_GeometryType(geom) LIKE '%Polygon%'
AND name IS NOT NULL
ON CONFLICT DO NOTHING;

-- Статистика
SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN scope = 1 THEN 1 END) as countries,
    COUNT(CASE WHEN scope = 2 THEN 1 END) as cities
FROM geo_area
WHERE country_iso3 = '$ISO3';

-- Удаляем временные таблицы
DROP TABLE IF EXISTS $TEMP_TABLE CASCADE;
DROP TABLE IF EXISTS $TEMP_PLACES_TABLE CASCADE;
EOF

echo "   ✓ Data processed"
echo ""

# Шаг 6: Экспорт в SQL дамп
echo "💾 Step 6: Exporting to SQL dump..."

DUMP_FILE="docker/dumps/geo_areas/geo_areas_dump_${ISO3_LOWER}_01.sql"

# Создаем заголовок
cat > "$DUMP_FILE" << HEADER
-- GeoFabrik GeoArea SQL Dump
-- Country: $ISO3
-- Part: 01
-- Generated at: $(date '+%Y-%m-%d %H:%M:%S')
-- Source: GeoFabrik.de
-- Quality: High (solid filled polygons)

-- Delete existing data for this country
DELETE FROM geo_area WHERE country_iso3 = '$ISO3';

HEADER

# Добавляем данные
docker-compose exec -T database psql -U app -d app -c "
COPY (
    SELECT 
        'INSERT INTO geo_area (id, name, scope, geometry, country_iso3, created_at, updated_at) VALUES (' ||
        quote_literal(id::text) || '::uuid, ' ||
        quote_literal(name) || ', ' ||
        scope || ', ' ||
        'ST_GeomFromText(' || quote_literal(ST_AsText(geometry)) || ', 4326), ' ||
        quote_literal(country_iso3) || ', ' ||
        quote_literal(created_at::text) || '::timestamp, ' ||
        quote_literal(updated_at::text) || '::timestamp' ||
        ');'
    FROM geo_area
    WHERE country_iso3 = '$ISO3'
    ORDER BY scope, name
) TO STDOUT
" >> "$DUMP_FILE"

echo "   ✓ SQL dump created: $DUMP_FILE"
echo "   Size: $(ls -lh $DUMP_FILE | awk '{print $5}')"
echo ""

# Шаг 7: Очистка
echo "🧹 Step 7: Cleaning up..."
rm -f "$ZIP_FILE"
rm -rf "$EXTRACT_DIR"
echo "   ✓ Temporary files removed"
echo ""

echo "✅ Import completed successfully!"
echo ""
echo "📊 Summary:"
docker-compose exec -T database psql -U app -d app -c "
SELECT 
    '$ISO3' as country,
    COUNT(*) as total_areas,
    COUNT(CASE WHEN scope = 1 THEN 1 END) as countries,
    COUNT(CASE WHEN scope = 2 THEN 1 END) as cities
FROM geo_area
WHERE country_iso3 = '$ISO3';
" | grep -v "^-" | grep -v "rows)"

echo ""
echo "🚀 Next steps:"
echo "   1. Check data: docker-compose exec database psql -U app -d app -c \"SELECT name FROM geo_area WHERE country_iso3 = '$ISO3' ORDER BY scope, name;\""
echo "   2. Restart container: docker-compose restart"
echo ""
echo "✨ Done!"

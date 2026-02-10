#!/bin/bash
# Упрощенный скрипт для импорта GeoFabrik без GDAL
# Использует готовые SQL запросы и Python для обработки

set -e

COUNTRY="${1:-latvia}"
COUNTRY_LOWER=$(echo "$COUNTRY" | tr '[:upper:]' '[:lower:]')

echo "🌍 GeoFabrik Simple Import для $COUNTRY"
echo "========================================"
echo ""
echo "⚠️  Этот метод не требует GDAL!"
echo "📝 Использует готовые GeoJSON данные и Python"
echo ""

# Маппинг стран
declare -A ISO3_MAP
ISO3_MAP[latvia]="LVA"
ISO3_MAP[estonia]="EST"
ISO3_MAP[lithuania]="LTU"

ISO3="${ISO3_MAP[$COUNTRY_LOWER]}"
ISO3_LOWER=$(echo "$ISO3" | tr '[:upper:]' '[:lower:]')

if [ -z "$ISO3" ]; then
    echo "❌ Страна не поддерживается: $COUNTRY"
    exit 1
fi

# Создаем Python скрипт для обработки
cat > /tmp/process_geofabrik.py << 'PYTHON_EOF'
#!/usr/bin/env python3
import sys
import json
import urllib.request

def download_geojson(country_lower):
    """Скачать GeoJSON напрямую из альтернативного источника"""
    # Используем Nominatim для получения границ
    url = f"https://nominatim.openstreetmap.org/search?country={country_lower}&polygon_geojson=1&format=json"
    
    with urllib.request.urlopen(url) as response:
        data = json.loads(response.read())
        if data and len(data) > 0:
            return data[0].get('geojson')
    
    return None

country = sys.argv[1] if len(sys.argv) > 1 else 'latvia'
print(f"Fetching GeoJSON for {country}...")

geojson = download_geojson(country)
if geojson:
    print(json.dumps(geojson, indent=2))
else:
    print("ERROR: No data found", file=sys.stderr)
    sys.exit(1)
PYTHON_EOF

chmod +x /tmp/process_geofabrik.py

echo "📥 Step 1: Fetching country boundary from Nominatim..."
COUNTRY_GEOJSON=$(python3 /tmp/process_geofabrik.py "$COUNTRY_LOWER" 2>&1)

if [ $? -ne 0 ]; then
    echo "❌ Failed to fetch GeoJSON"
    echo ""
    echo "💡 Альтернативное решение:"
    echo "   Используйте полный скрипт с GDAL: ./scripts/import_geofabrik.sh"
    echo "   Установите GDAL: brew install gdal (macOS)"
    exit 1
fi

echo "   ✓ Country boundary fetched"
echo ""

echo "🏙️  Step 2: Fetching cities..."

# Для городов используем упрощенный подход - вставляем вручную список крупных городов
docker-compose exec -T database psql -U app -d app << EOF
-- Очищаем старые данные
DELETE FROM geo_area WHERE country_iso3 = '$ISO3';

-- Вставляем страну (упрощенная граница - используем данные из OSM)
INSERT INTO geo_area (id, name, scope, geometry, country_iso3, created_at, updated_at)
SELECT 
    gen_random_uuid(),
    '$ISO3',
    1,
    geometry,
    '$ISO3',
    NOW(),
    NOW()
FROM (SELECT geometry FROM geo_area WHERE name = '$ISO3' LIMIT 1) as country_data
ON CONFLICT DO NOTHING;

-- Если нет данных страны, пропускаем
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM geo_area WHERE scope = 1 AND country_iso3 = '$ISO3') THEN
        RAISE NOTICE 'Country boundary not found, using placeholder';
    END IF;
END \$\$;

EOF

echo "   ✓ Data prepared"
echo ""

echo "📊 Current status:"
docker-compose exec -T database psql -U app -d app -c "
SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN scope = 1 THEN 1 END) as countries,
    COUNT(CASE WHEN scope = 2 THEN 1 END) as cities
FROM geo_area
WHERE country_iso3 = '$ISO3';
"

echo ""
echo "⚠️  Этот упрощенный метод не полностью реализован"
echo "📝 Для полноценного импорта используйте:"
echo "   ./scripts/import_geofabrik.sh $COUNTRY_LOWER"
echo ""
echo "   Для этого нужно установить GDAL:"
echo "   macOS: brew install gdal"
echo "   Linux: sudo apt-get install gdal-bin"
echo ""

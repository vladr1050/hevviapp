#!/bin/bash
# Быстрое исправление геометрии текущих данных

set -e

echo "🔧 Быстрое исправление геометрии GeoArea"
echo "======================================="
echo ""
echo "Применяем ST_Buffer для создания заполненных полигонов"
echo ""

docker-compose exec -T database psql -U app -d app << 'EOF'
-- Создаем backup
CREATE TABLE IF NOT EXISTS geo_area_backup_original AS 
SELECT * FROM geo_area WHERE NOT EXISTS (
    SELECT 1 FROM geo_area_backup_original LIMIT 1
);

-- Применяем исправление для городов:
-- 1. ST_Buffer(500м) для заполнения gaps между частями
-- 2. ST_Union для объединения соприкасающихся частей
-- 3. ST_SimplifyPreserveTopology для оптимизации

DO $$
DECLARE
    city_rec RECORD;
    new_geom geometry;
    old_parts int;
    new_parts int;
BEGIN
    RAISE NOTICE 'Starting geometry fix...';
    
    FOR city_rec IN 
        SELECT id, name, geometry 
        FROM geo_area 
        WHERE scope = 2 
        ORDER BY name
    LOOP
        old_parts := ST_NumGeometries(city_rec.geometry);
        
        -- Применяем буфер + simplify + обратный буфер
        new_geom := ST_Multi(
            ST_Buffer(
                ST_SimplifyPreserveTopology(
                    ST_Buffer(city_rec.geometry::geography, 500)::geometry,
                    0.0005
                )::geography,
                -400  -- Уменьшаем обратно на 400м (оставляем +100м для заполнения gaps)
            )::geometry
        );
        
        new_parts := ST_NumGeometries(new_geom);
        
        UPDATE geo_area 
        SET geometry = new_geom 
        WHERE id = city_rec.id;
        
        RAISE NOTICE 'Fixed: % (parts: % -> %, area: %.2f km²)', 
            city_rec.name,
            old_parts,
            new_parts,
            ST_Area(new_geom::geography)/1000000;
    END LOOP;
    
    RAISE NOTICE 'Geometry fix completed!';
END $$;

-- Финальная проверка
SELECT 
    '✅ Результат:' as status,
    '' as name,
    '' as parts,
    '' as valid,
    '' as contains_center,
    '' as area_km2
UNION ALL
SELECT 
    '  ' || name as status,
    name,
    ST_NumGeometries(geometry)::text as parts,
    CASE WHEN ST_IsValid(geometry) THEN '✓' ELSE '✗' END as valid,
    CASE WHEN ST_Contains(geometry, ST_Centroid(geometry)) THEN '✓' ELSE '✗' END as contains_center,
    CAST(ST_Area(geometry::geography)/1000000 AS NUMERIC(10,2))::text as area_km2
FROM geo_area 
WHERE scope = 2
ORDER BY status, name;

-- Тест для Риги
SELECT 
    '' as line1,
    '🎯 Тест: Содержит ли Рига центр города?' as line2
UNION ALL
SELECT 
    '   Point: 24.105186, 56.949649 (Домский собор)',
    CASE 
        WHEN ST_Contains(
            geometry,
            ST_SetSRID(ST_MakePoint(24.105186, 56.949649), 4326)
        ) THEN '✅ ДА'
        ELSE '❌ НЕТ'
    END
FROM geo_area 
WHERE name = 'Riga';

EOF

echo ""
echo "✅ Исправление завершено!"
echo ""
echo "📊 Для проверки выполните:"
echo "   docker-compose exec database psql -U app -d app -c \"SELECT name, ST_NumGeometries(geometry) FROM geo_area WHERE scope = 2;\""
echo ""

<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Service\GeoArea;

use App\Entity\GeoArea;
use App\Service\GeoArea\Contract\GadmDataProviderInterface;
use App\Service\GeoArea\Contract\GeometryParserInterface;
use App\Service\GeoArea\DTO\OsmAreaDto;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Парсер GeoArea используя GADM (Global Administrative Areas Database)
 * 
 * GADM предоставляет высококачественные административные границы
 */
class GadmGeoAreaParser
{
    public function __construct(
        private readonly GadmDataProviderInterface $gadmProvider,
        private readonly GeometryParserInterface $geometryParser,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Парсить страну используя GADM с загрузкой всех муниципалитетов как городов
     *
     * @param string $iso3Code ISO3 код страны
     * @param int $cityAdminLevel Уровень административных единиц для городов (обычно 2-3)
     * @return OsmAreaDto[]
     */
    public function parseCountryWithAllCities(string $iso3Code, int $cityAdminLevel = 2): array
    {
        $this->logger->info('Starting GADM parsing (all cities)', [
            'iso3' => $iso3Code,
            'admin_level' => $cityAdminLevel,
        ]);

        $areas = [];

        // 1. Загружаем границу страны (ADM0)
        $countryArea = $this->parseCountryBoundary($iso3Code);
        if ($countryArea !== null) {
            $areas[] = $countryArea;
        }

        // 2. Загружаем все административные единицы указанного уровня как города
        $cityAreas = $this->parseAllAdminUnitsAsCities($iso3Code, $cityAdminLevel);
        $areas = array_merge($areas, $cityAreas);

        $this->logger->info('GADM parsing completed', [
            'total_areas' => count($areas),
            'cities_found' => count($cityAreas),
        ]);

        return $areas;
    }

    /**
     * Парсить страну используя GADM (старый метод с конкретными городами)
     *
     * @param string $iso3Code ISO3 код страны
     * @param array $cityPoints Массив городов с координатами: ['name' => '', 'lat' => 0, 'lon' => 0]
     * @return OsmAreaDto[]
     */
    public function parseCountryWithCities(string $iso3Code, array $cityPoints): array
    {
        $this->logger->info('Starting GADM parsing', [
            'iso3' => $iso3Code,
            'cities_count' => count($cityPoints),
        ]);

        $areas = [];

        // 1. Загружаем границу страны (ADM0)
        $countryArea = $this->parseCountryBoundary($iso3Code);
        if ($countryArea !== null) {
            $areas[] = $countryArea;
        }

        // 2. Загружаем административные уровни для поиска полигонов городов
        // Пробуем уровни от детального к общему: ADM4 -> ADM3 -> ADM2 -> ADM1
        $allFeatures = $this->loadAllAdminLevels($iso3Code);

        // 3. Для каждого города находим наименьший содержащий полигон
        foreach ($cityPoints as $cityPoint) {
            try {
                $cityArea = $this->findCityPolygon($cityPoint, $allFeatures, $iso3Code);
                if ($cityArea !== null) {
                    $areas[] = $cityArea;
                    $this->logger->info('City polygon found', [
                        'city' => $cityPoint['name'],
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->warning('Failed to find polygon for city', [
                    'city' => $cityPoint['name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('GADM parsing completed', [
            'total_areas' => count($areas),
            'cities_found' => count($areas) - 1,
        ]);

        return $areas;
    }

    /**
     * Получить границу страны (ADM0)
     */
    private function parseCountryBoundary(string $iso3Code): ?OsmAreaDto
    {
        try {
            $geoJson = $this->gadmProvider->getAdminBoundaries($iso3Code, 0);
            
            if (empty($geoJson['features'])) {
                return null;
            }

            $feature = $geoJson['features'][0];
            $geometryWkt = $this->geometryParser->geoJsonToWkt($feature);

            $countryName = $feature['properties']['COUNTRY'] 
                ?? $feature['properties']['NAME_0']
                ?? $iso3Code;

            return new OsmAreaDto(
                name: $countryName,
                countryISO3: $iso3Code,
                scope: GeoArea::SCOPE['COUNTRY'],
                geometryWkt: $geometryWkt,
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to load country boundary', [
                'iso3' => $iso3Code,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Загрузить все доступные административные уровни
     */
    private function loadAllAdminLevels(string $iso3Code): array
    {
        $allFeatures = [];
        
        // Пробуем загрузить уровни от детального к общему
        foreach ([4, 3, 2, 1] as $level) {
            try {
                $this->logger->info('Loading GADM level', [
                    'iso3' => $iso3Code,
                    'level' => $level,
                ]);

                $geoJson = $this->gadmProvider->getAdminBoundaries($iso3Code, $level);
                
                if (!empty($geoJson['features'])) {
                    foreach ($geoJson['features'] as $feature) {
                        $feature['_gadm_level'] = $level;
                        $allFeatures[] = $feature;
                    }
                    
                    $this->logger->info('Level loaded', [
                        'level' => $level,
                        'features' => count($geoJson['features']),
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->info('Level not available or failed', [
                    'level' => $level,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('All levels loaded', [
            'total_features' => count($allFeatures),
        ]);

        return $allFeatures;
    }

    /**
     * Найти полигон для города используя координаты
     */
    private function findCityPolygon(array $cityPoint, array $allFeatures, string $iso3Code): ?OsmAreaDto
    {
        $lat = $cityPoint['lat'];
        $lon = $cityPoint['lon'];
        $cityName = $cityPoint['name'];

        // Используем PostGIS для точного поиска
        $polygon = $this->findPolygonViaPostGIS($allFeatures, $lat, $lon);

        if ($polygon === null) {
            return null;
        }

        $geometryWkt = $this->geometryParser->geoJsonToWkt($polygon);

        return new OsmAreaDto(
            name: $cityName,
            countryISO3: $iso3Code,
            scope: GeoArea::SCOPE['CITY'],
            geometryWkt: $geometryWkt,
        );
    }

    /**
     * Найти наименьший полигон содержащий точку через PostGIS
     */
    private function findPolygonViaPostGIS(array $features, float $lat, float $lon): ?array
    {
        // Создаем временную таблицу
        $tempTable = 'temp_gadm_search_' . uniqid();
        
        $this->connection->executeStatement("
            CREATE TEMP TABLE {$tempTable} (
                id SERIAL PRIMARY KEY,
                geom GEOMETRY(MULTIPOLYGON, 4326),
                feature_json TEXT,
                area DOUBLE PRECISION
            )
        ");

        // Вставляем все features
        foreach ($features as $index => $feature) {
            if (!isset($feature['geometry'])) {
                continue;
            }

            $geoJsonStr = json_encode($feature['geometry']);
            $featureJson = json_encode($feature);
            
            $this->connection->executeStatement("
                INSERT INTO {$tempTable} (geom, feature_json, area)
                VALUES (
                    ST_Multi(ST_GeomFromGeoJSON(?)),
                    ?,
                    ST_Area(ST_Multi(ST_GeomFromGeoJSON(?))::geography)
                )
            ", [$geoJsonStr, $featureJson, $geoJsonStr]);
        }

        // Находим наименьший полигон содержащий точку
        $result = $this->connection->fetchAssociative("
            SELECT feature_json
            FROM {$tempTable}
            WHERE ST_Contains(
                geom,
                ST_SetSRID(ST_MakePoint(?, ?), 4326)
            )
            ORDER BY area ASC
            LIMIT 1
        ", [$lon, $lat]);

        // Удаляем временную таблицу
        $this->connection->executeStatement("DROP TABLE IF EXISTS {$tempTable}");

        if ($result === false) {
            return null;
        }

        return json_decode($result['feature_json'], true);
    }

    /**
     * Загрузить все административные единицы указанного уровня как города
     */
    private function parseAllAdminUnitsAsCities(string $iso3Code, int $adminLevel): array
    {
        $areas = [];

        try {
            $this->logger->info('Loading all admin units as cities', [
                'iso3' => $iso3Code,
                'level' => $adminLevel,
            ]);

            $geoJson = $this->gadmProvider->getAdminBoundaries($iso3Code, $adminLevel);
            
            if (empty($geoJson['features'])) {
                $this->logger->warning('No features found at level', [
                    'level' => $adminLevel,
                ]);
                return [];
            }

            foreach ($geoJson['features'] as $feature) {
                try {
                    // Извлекаем название
                    $name = $feature['properties']['NAME_' . $adminLevel] 
                        ?? $feature['properties']['VARNAME_' . $adminLevel]
                        ?? $feature['properties']['NAME_1']
                        ?? 'Unknown';

                    // Конвертируем геометрию
                    $geometryWkt = $this->geometryParser->geoJsonToWkt($feature);

                    $areas[] = new OsmAreaDto(
                        name: $name,
                        countryISO3: $iso3Code,
                        scope: GeoArea::SCOPE['CITY'],
                        geometryWkt: $geometryWkt,
                    );

                    $this->logger->debug('Admin unit added as city', [
                        'name' => $name,
                        'level' => $adminLevel,
                    ]);
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to process admin unit', [
                        'name' => $feature['properties']['NAME_' . $adminLevel] ?? 'Unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->logger->info('Admin units loaded', [
                'count' => count($areas),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to load admin level', [
                'level' => $adminLevel,
                'error' => $e->getMessage(),
            ]);
        }

        return $areas;
    }
}

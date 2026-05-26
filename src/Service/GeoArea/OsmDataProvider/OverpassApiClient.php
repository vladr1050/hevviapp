<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains
 * the property of SIA SLYFOX, its suppliers and Customers,
 * if any.  The intellectual and technical concepts contained
 * herein are proprietary to SIA SLYFOX
 * its Suppliers and Customers are protected by trade secret or copyright law.
 *
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained.
 */

namespace App\Service\GeoArea\OsmDataProvider;

use App\Service\GeoArea\Contract\OsmDataProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Клиент для работы с Overpass API (OpenStreetMap)
 */
class OverpassApiClient implements OsmDataProviderInterface
{
    // Список альтернативных Overpass API endpoints (fallback)
    private const OVERPASS_API_URLS = [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.kumi.systems/api/interpreter',
        'https://overpass.openstreetmap.ru/api/interpreter',
    ];
    
    private const REQUEST_TIMEOUT = 180; // 3 минуты (уменьшили с 5)
    private const RETRY_DELAY = 5; // 5 секунд задержка между попытками
    private const MAX_RETRIES = 2; // Максимум 2 попытки
    
    private int $currentEndpointIndex = 0;
    
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getCountryBoundary(string $relationId): array
    {
        // Используем 'out geom;' который возвращает упрощенную геометрию
        // Это намного быстрее, чем рекурсивная загрузка всех элементов
        $query = sprintf(
            '[out:json][timeout:%d];relation(%s);out geom;',
            self::REQUEST_TIMEOUT,
            $relationId
        );

        $this->logger->info('Fetching country boundary from OSM', [
            'relation_id' => $relationId,
            'query' => $query,
        ]);

        $response = $this->executeQueryWithRetry($query);
        
        if (empty($response['elements'])) {
            throw new \RuntimeException("Country boundary not found for relation ID: {$relationId}");
        }

        $relation = $response['elements'][0];
        
        // Проверяем, что получили relation
        if ($relation['type'] !== 'relation') {
            throw new \RuntimeException("Expected relation, got: " . $relation['type']);
        }

        return $this->convertToGeoJson($relation);
    }

    public function getCitiesInCountry(string $countryRelationId, int $adminLevel = 8): array
    {
        // Преобразуем relation ID в area ID (добавляем 3600000000)
        $areaId = 3600000000 + (int)$countryRelationId;
        
        // Загружаем ТОЛЬКО государственные города (valstspilsētas)
        // admin_level=5 + border_type="city" дает 7 государственных городов Латвии
        // Без фильтра border_type загрузятся все 50 единиц (города + novadi), что исчерпывает память
        $query = sprintf(
            '[out:json][timeout:%d];area(%s)->.country;relation["boundary"="administrative"]["admin_level"="%d"]["border_type"="city"](area.country);out geom;',
            self::REQUEST_TIMEOUT,
            $areaId,
            $adminLevel
        );

        $this->logger->info('Fetching cities from OSM', [
            'country_relation_id' => $countryRelationId,
            'area_id' => $areaId,
            'filter' => 'admin_level=5 + border_type=city',
            'query' => $query,
        ]);

        $response = $this->executeQueryWithRetry($query);
        
        if (empty($response['elements'])) {
            $this->logger->warning('No cities found', [
                'country_relation_id' => $countryRelationId,
                'admin_level' => $adminLevel,
            ]);
            
            return [];
        }

        $this->logger->info('Cities fetched', [
            'count' => count($response['elements']),
        ]);

        // Конвертируем каждую relation в GeoJSON
        $cities = [];
        foreach ($response['elements'] as $element) {
            if ($element['type'] !== 'relation') {
                continue;
            }
            
            try {
                $cities[] = $this->convertToGeoJson($element);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to process city', [
                    'city_name' => $element['tags']['name'] ?? 'Unknown',
                    'relation_id' => $element['id'] ?? 'Unknown',
                    'error' => $e->getMessage(),
                ]);
                // Продолжаем обработку других городов
            }
        }

        return $cities;
    }

    public function getAdminUnitsInCountry(
        string $countryRelationId,
        int $adminLevel,
        ?string $excludeBorderType = null,
        ?string $nameRegex = null,
    ): array {
        $areaId = 3600000000 + (int)$countryRelationId;
        $relationFilter = $this->buildAdminUnitRelationFilter($adminLevel, $excludeBorderType, $nameRegex);

        // Сначала только id/tags — один большой out geom по всей стране легко съедает 128M PHP.
        $listQuery = sprintf(
            '[out:json][timeout:%d];area(%s)->.country;%s(area.country);out tags;',
            self::REQUEST_TIMEOUT,
            $areaId,
            $relationFilter,
        );

        $this->logger->info('Listing admin unit relation IDs from OSM', [
            'country_relation_id' => $countryRelationId,
            'area_id' => $areaId,
            'admin_level' => $adminLevel,
            'exclude_border_type' => $excludeBorderType,
            'name_regex' => $nameRegex,
            'query' => $listQuery,
        ]);

        $listResponse = $this->executeQueryWithRetry($listQuery);
        $relationIds = [];
        foreach ($listResponse['elements'] ?? [] as $element) {
            if (($element['type'] ?? '') === 'relation' && isset($element['id'])) {
                $relationIds[] = (int)$element['id'];
            }
        }
        unset($listResponse);

        if ($relationIds === []) {
            $this->logger->warning('No admin units found', [
                'country_relation_id' => $countryRelationId,
                'admin_level' => $adminLevel,
            ]);

            return [];
        }

        $this->logger->info('Fetching admin unit geometries one-by-one', [
            'relation_count' => count($relationIds),
            'admin_level' => $adminLevel,
        ]);

        $units = [];
        $lastIndex = count($relationIds) - 1;
        foreach ($relationIds as $index => $relationId) {
            $geomQuery = sprintf(
                '[out:json][timeout:%d];relation(%d);out geom;',
                self::REQUEST_TIMEOUT,
                $relationId,
            );

            $response = null;
            try {
                $response = $this->executeQueryWithRetry($geomQuery);

                foreach ($response['elements'] ?? [] as $element) {
                    if (($element['type'] ?? '') !== 'relation') {
                        continue;
                    }

                    try {
                        $units[] = $this->convertToGeoJson($element);
                    } catch (\Exception $e) {
                        $this->logger->warning('Failed to process admin unit', [
                            'name' => $element['tags']['name'] ?? 'Unknown',
                            'relation_id' => $element['id'] ?? 'Unknown',
                            'admin_level' => $adminLevel,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning('Failed to fetch admin unit geometry', [
                    'relation_id' => $relationId,
                    'admin_level' => $adminLevel,
                    'error' => $e->getMessage(),
                ]);
            }
            unset($response);

            if ($index < $lastIndex) {
                usleep(200_000);
            }
        }

        $this->logger->info('Admin units fetched', [
            'count' => count($units),
            'admin_level' => $adminLevel,
        ]);

        return $units;
    }

    private function buildAdminUnitRelationFilter(
        int $adminLevel,
        ?string $excludeBorderType,
        ?string $nameRegex,
    ): string {
        $relationFilter = sprintf(
            'relation["boundary"="administrative"]["admin_level"="%d"]',
            $adminLevel,
        );
        if ($excludeBorderType !== null && $excludeBorderType !== '') {
            // LV: novadi и valstspilsētas оба admin_level=5; города помечены border_type=city.
            $relationFilter .= sprintf('["border_type"!="%s"]', $excludeBorderType);
        }
        if ($nameRegex !== null && $nameRegex !== '') {
            $relationFilter .= sprintf('["name"~"%s",i]', str_replace('"', '\\"', $nameRegex));
        }

        return $relationFilter;
    }

    /**
     * Выполнить запрос с retry и fallback на альтернативные endpoints
     */
    private function executeQueryWithRetry(string $query, int $attempt = 1): array
    {
        $lastException = null;
        
        // Пробуем все доступные endpoints
        foreach (self::OVERPASS_API_URLS as $index => $apiUrl) {
            try {
                $this->logger->info('Trying Overpass API endpoint', [
                    'endpoint' => $apiUrl,
                    'attempt' => $attempt,
                ]);
                
                $result = $this->executeQuery($query, $apiUrl);
                
                // Если успешно - сохраняем этот endpoint как основной
                $this->currentEndpointIndex = $index;
                
                return $result;
                
            } catch (\RuntimeException $e) {
                $lastException = $e;
                
                // Если это 504 таймаут и есть еще попытки - пробуем другой endpoint
                if (str_contains($e->getMessage(), '504')) {
                    $this->logger->warning('Endpoint timed out, trying next', [
                        'endpoint' => $apiUrl,
                        'error' => $e->getMessage(),
                    ]);
                    
                    // Короткая задержка перед следующей попыткой
                    sleep(2);
                    continue;
                }
                
                // Для других ошибок - пробуем следующий endpoint сразу
                $this->logger->warning('Endpoint failed, trying next', [
                    'endpoint' => $apiUrl,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }
        
        // Если все endpoints провалились и есть еще попытки - retry с задержкой
        if ($attempt < self::MAX_RETRIES) {
            $this->logger->info('All endpoints failed, retrying after delay', [
                'attempt' => $attempt,
                'delay' => self::RETRY_DELAY,
            ]);
            
            sleep(self::RETRY_DELAY);
            return $this->executeQueryWithRetry($query, $attempt + 1);
        }
        
        // Все попытки исчерпаны
        throw new \RuntimeException(
            'All Overpass API endpoints failed after ' . self::MAX_RETRIES . ' attempts. ' .
            'Last error: ' . ($lastException?->getMessage() ?? 'Unknown error')
        );
    }

    /**
     * Выполнить запрос к Overpass API
     */
    private function executeQuery(string $query, ?string $apiUrl = null): array
    {
        $apiUrl = $apiUrl ?? self::OVERPASS_API_URLS[0];
        
        $ch = curl_init();
        
        // Формируем данные для POST запроса правильно
        $postData = http_build_query(['data' => $query]);
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_USERAGENT => 'Hevii BackOffice GeoArea Parser/1.0',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);

        if ($error) {
            $this->logger->error('Overpass API curl error', [
                'error' => $error,
                'query' => $query,
            ]);
            throw new \RuntimeException("Overpass API request failed: {$error}");
        }

        if ($httpCode !== 200) {
            $errorMessage = is_string($response) ? substr($response, 0, 500) : 'No response body';
            
            $this->logger->error('Overpass API returned non-200 status', [
                'http_code' => $httpCode,
                'query' => $query,
                'response' => $errorMessage,
            ]);
            
            throw new \RuntimeException(
                "Overpass API returned HTTP {$httpCode}. Response: {$errorMessage}"
            );
        }

        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Failed to parse JSON response', [
                'error' => json_last_error_msg(),
                'response' => substr($response, 0, 500),
            ]);
            throw new \RuntimeException('Failed to parse Overpass API response: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Обогатить relation геометрией из загруженных ways и nodes
     */
    private function enrichRelationWithGeometry(array $relation, array $ways, array $nodes): array
    {
        if (!isset($relation['members'])) {
            return $relation;
        }

        // Обогащаем каждый member геометрией
        foreach ($relation['members'] as &$member) {
            if ($member['type'] === 'way' && isset($member['ref'])) {
                $wayId = $member['ref'];
                
                if (isset($ways[$wayId]) && isset($ways[$wayId]['nodes'])) {
                    $way = $ways[$wayId];
                    $geometry = [];
                    
                    // Собираем координаты из nodes
                    foreach ($way['nodes'] as $nodeId) {
                        if (isset($nodes[$nodeId])) {
                            $node = $nodes[$nodeId];
                            $geometry[] = [
                                'lat' => $node['lat'],
                                'lon' => $node['lon'],
                            ];
                        }
                    }
                    
                    if (!empty($geometry)) {
                        $member['geometry'] = $geometry;
                    }
                }
            }
        }

        return $relation;
    }

    /**
     * Конвертировать OSM элемент в GeoJSON
     */
    private function convertToGeoJson(array $element): array
    {
        $geometry = $this->buildGeometry($element);
        
        return [
            'type' => 'Feature',
            'properties' => [
                'name' => $element['tags']['name'] ?? 'Unknown',
                'name:en' => $element['tags']['name:en'] ?? null,
                'name:ru' => $element['tags']['name:ru'] ?? null,
                'admin_level' => $element['tags']['admin_level'] ?? null,
                'osm_id' => $element['id'] ?? null,
            ],
            'geometry' => $geometry,
        ];
    }

    /**
     * Построить геометрию из OSM элемента
     */
    private function buildGeometry(array $element): array
    {
        if ($element['type'] !== 'relation') {
            throw new \RuntimeException('Only relation elements are supported');
        }

        if (!isset($element['members']) || empty($element['members'])) {
            throw new \RuntimeException('Relation has no members');
        }

        // OSM relation membership: each "outer" / "inner" member is a *way segment*.
        // To get a closed polygon ring we must concatenate ways that share endpoints.
        $outerWays = [];
        $innerWays = [];

        foreach ($element['members'] as $member) {
            if (($member['type'] ?? '') !== 'way') {
                continue;
            }
            if (!isset($member['geometry']) || !is_array($member['geometry'])) {
                continue;
            }

            $coordinates = [];
            foreach ($member['geometry'] as $point) {
                if (isset($point['lon'], $point['lat'])) {
                    $coordinates[] = [(float)$point['lon'], (float)$point['lat']];
                }
            }

            if (count($coordinates) < 2) {
                continue;
            }

            $role = $member['role'] ?? '';
            if ($role === 'outer' || $role === '') {
                $outerWays[] = $coordinates;
            } elseif ($role === 'inner') {
                $innerWays[] = $coordinates;
            }
        }

        $outerRings = $this->assembleRings($outerWays);
        $innerRings = $this->assembleRings($innerWays);

        if (empty($outerRings)) {
            throw new \RuntimeException('No valid outer rings assembled from relation ways');
        }

        // Distribute inner rings into the outer ring that contains them.
        $polygons = array_map(static fn(array $ring): array => [$ring], $outerRings);

        foreach ($innerRings as $innerRing) {
            $hostIndex = $this->findContainingPolygon($innerRing, $outerRings);
            if ($hostIndex === null) {
                $hostIndex = 0;
            }
            $polygons[$hostIndex][] = $innerRing;
        }

        return [
            'type' => 'MultiPolygon',
            'coordinates' => $polygons,
        ];
    }

    /**
     * Stitch unordered way segments into closed rings by matching shared endpoints.
     * Each input segment is an array of [lon, lat] points (≥2 points each).
     *
     * @param array<int, array<int, array{0: float, 1: float}>> $ways
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    private function assembleRings(array $ways): array
    {
        $rings = [];
        $used = array_fill(0, count($ways), false);

        for ($i = 0, $iMax = count($ways); $i < $iMax; $i++) {
            if ($used[$i]) {
                continue;
            }

            $ring = $ways[$i];
            $used[$i] = true;

            $progress = true;
            while ($progress && !$this->ringIsClosed($ring)) {
                $progress = false;

                for ($j = 0; $j < $iMax; $j++) {
                    if ($used[$j]) {
                        continue;
                    }

                    $candidate = $ways[$j];
                    $ringStart = $ring[0];
                    $ringEnd = end($ring);
                    $candStart = $candidate[0];
                    $candEnd = end($candidate);

                    if ($this->pointsEqual($ringEnd, $candStart)) {
                        array_pop($ring);
                        foreach ($candidate as $point) {
                            $ring[] = $point;
                        }
                        $used[$j] = true;
                        $progress = true;
                        break;
                    }

                    if ($this->pointsEqual($ringEnd, $candEnd)) {
                        array_pop($ring);
                        $reversed = array_reverse($candidate);
                        foreach ($reversed as $point) {
                            $ring[] = $point;
                        }
                        $used[$j] = true;
                        $progress = true;
                        break;
                    }

                    if ($this->pointsEqual($ringStart, $candEnd)) {
                        $tail = $ring;
                        $ring = $candidate;
                        array_pop($ring);
                        foreach ($tail as $point) {
                            $ring[] = $point;
                        }
                        $used[$j] = true;
                        $progress = true;
                        break;
                    }

                    if ($this->pointsEqual($ringStart, $candStart)) {
                        $reversed = array_reverse($candidate);
                        $tail = $ring;
                        $ring = $reversed;
                        array_pop($ring);
                        foreach ($tail as $point) {
                            $ring[] = $point;
                        }
                        $used[$j] = true;
                        $progress = true;
                        break;
                    }
                }
            }

            if (!$this->ringIsClosed($ring)) {
                $this->logger->warning('Skipping unclosed OSM ring', [
                    'relation_role_points' => count($ring),
                ]);
                continue;
            }

            if (count($ring) >= 4) {
                $rings[] = $ring;
            }
        }

        return $rings;
    }

    /**
     * @param array<int, array{0: float, 1: float}> $ring
     */
    private function ringIsClosed(array $ring): bool
    {
        if (count($ring) < 4) {
            return false;
        }
        $first = $ring[0];
        $last = end($ring);

        return $this->pointsEqual($first, $last);
    }

    /**
     * @param array{0: float, 1: float} $a
     * @param array{0: float, 1: float} $b
     */
    private function pointsEqual(array $a, array $b): bool
    {
        return abs($a[0] - $b[0]) < 1e-9 && abs($a[1] - $b[1]) < 1e-9;
    }

    /**
     * Pick an outer ring that contains the first point of $innerRing (cheap bbox + ray-casting).
     *
     * @param array<int, array{0: float, 1: float}> $innerRing
     * @param array<int, array<int, array{0: float, 1: float}>> $outerRings
     */
    private function findContainingPolygon(array $innerRing, array $outerRings): ?int
    {
        if (empty($innerRing)) {
            return null;
        }
        $probe = $innerRing[0];

        foreach ($outerRings as $index => $outer) {
            if ($this->pointInRing($probe, $outer)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array{0: float, 1: float} $point
     * @param array<int, array{0: float, 1: float}> $ring
     */
    private function pointInRing(array $point, array $ring): bool
    {
        $inside = false;
        $count = count($ring);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $ring[$i][0];
            $yi = $ring[$i][1];
            $xj = $ring[$j][0];
            $yj = $ring[$j][1];

            $intersect = (($yi > $point[1]) !== ($yj > $point[1]))
                && ($point[0] < ($xj - $xi) * ($point[1] - $yi) / (($yj - $yi) ?: 1e-12) + $xi);

            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}

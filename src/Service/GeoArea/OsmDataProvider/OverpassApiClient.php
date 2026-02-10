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

        // Разделяем members на outer и inner
        $outerRings = [];
        $innerRings = [];
        
        foreach ($element['members'] as $member) {
            if (!isset($member['geometry']) || !is_array($member['geometry'])) {
                continue;
            }
            
            $coordinates = [];
            foreach ($member['geometry'] as $point) {
                if (isset($point['lon'], $point['lat'])) {
                    $coordinates[] = [$point['lon'], $point['lat']];
                }
            }
            
            // Замыкаем кольцо
            if (!empty($coordinates)) {
                $first = $coordinates[0];
                $last = end($coordinates);
                
                if ($first[0] !== $last[0] || $first[1] !== $last[1]) {
                    $coordinates[] = $first;
                }
            }
            
            // Минимум 4 точки для валидного кольца
            if (count($coordinates) >= 4) {
                if ($member['role'] === 'outer') {
                    $outerRings[] = $coordinates;
                } elseif ($member['role'] === 'inner') {
                    $innerRings[] = $coordinates;
                }
            }
        }

        if (empty($outerRings)) {
            throw new \RuntimeException('No valid outer rings found in relation');
        }

        // Строим правильный MultiPolygon
        // Если несколько outer rings - это несколько отдельных полигонов
        $polygons = [];
        
        if (count($outerRings) === 1) {
            // Один внешний контур - строим один Polygon с дырками
            $polygonRings = [$outerRings[0]]; // Первое кольцо - внешнее
            
            // Добавляем все внутренние кольца (дырки)
            foreach ($innerRings as $innerRing) {
                $polygonRings[] = $innerRing;
            }
            
            return [
                'type' => 'MultiPolygon',
                'coordinates' => [[$polygonRings]], // MultiPolygon всегда для консистентности
            ];
        } else {
            // Несколько outer rings - MultiPolygon
            // В идеале нужно распределить inner rings по outer rings,
            // но для простоты добавляем все inner к первому outer
            foreach ($outerRings as $index => $outerRing) {
                if ($index === 0 && !empty($innerRings)) {
                    // К первому полигону добавляем все дырки
                    $polygonRings = [$outerRing];
                    foreach ($innerRings as $innerRing) {
                        $polygonRings[] = $innerRing;
                    }
                    $polygons[] = $polygonRings;
                } else {
                    // Остальные полигоны без дырок
                    $polygons[] = [$outerRing];
                }
            }
            
            return [
                'type' => 'MultiPolygon',
                'coordinates' => $polygons,
            ];
        }
    }
}

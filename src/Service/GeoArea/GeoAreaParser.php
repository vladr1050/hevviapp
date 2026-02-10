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

namespace App\Service\GeoArea;

use App\Entity\GeoArea;
use App\Service\GeoArea\Contract\GeoAreaDataDumperInterface;
use App\Service\GeoArea\Contract\GeometryParserInterface;
use App\Service\GeoArea\Contract\OsmDataProviderInterface;
use App\Service\GeoArea\DTO\CountryConfigDto;
use App\Service\GeoArea\DTO\OsmAreaDto;
use Psr\Log\LoggerInterface;

/**
 * Основной парсер для загрузки GeoArea из OpenStreetMap
 * 
 * Реализует принцип Single Responsibility - координирует работу других сервисов,
 * но не выполняет низкоуровневую работу сам
 */
class GeoAreaParser
{
    public function __construct(
        private readonly OsmDataProviderInterface $osmDataProvider,
        private readonly GeometryParserInterface $geometryParser,
        private readonly GeoAreaDataDumperInterface $dataDumper,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Парсить страну и ее города из OSM
     *
     * @param CountryConfigDto $config Конфигурация страны
     * @return OsmAreaDto[] Массив распарсенных областей (страна + города)
     */
    public function parseCountry(CountryConfigDto $config): array
    {
        $this->logger->info('Starting to parse country', [
            'country' => $config->name,
            'iso3' => $config->iso3Code,
        ]);

        $areas = [];

        // 1. Парсим границы страны
        $countryArea = $this->parseCountryBoundary($config);
        $areas[] = $countryArea;

        $this->logger->info('Country boundary parsed successfully', [
            'country' => $config->name,
        ]);

        // 2. Парсим города
        $cities = $this->parseCities($config);
        $areas = array_merge($areas, $cities);

        $this->logger->info('Country parsing completed', [
            'country' => $config->name,
            'total_areas' => count($areas),
            'cities_count' => count($cities),
        ]);

        return $areas;
    }

    /**
     * Парсить несколько стран
     *
     * @param CountryConfigDto[] $countries
     * @return OsmAreaDto[]
     */
    public function parseCountries(array $countries): array
    {
        $allAreas = [];

        foreach ($countries as $country) {
            try {
                $areas = $this->parseCountry($country);
                $allAreas = array_merge($allAreas, $areas);
            } catch (\Exception $e) {
                $this->logger->error('Failed to parse country', [
                    'country' => $country->name,
                    'error' => $e->getMessage(),
                ]);
                
                throw $e;
            }
        }

        return $allAreas;
    }

    /**
     * Парсить и сохранить в дамп
     *
     * @param CountryConfigDto[] $countries
     * @param string $outputPath
     */
    public function parseAndDump(array $countries, string $outputPath): void
    {
        $areas = $this->parseCountries($countries);
        $this->dataDumper->generateSqlDump($areas, $outputPath);
    }

    /**
     * Парсить границы страны
     */
    private function parseCountryBoundary(CountryConfigDto $config): OsmAreaDto
    {
        $this->logger->info('Fetching country boundary', [
            'country' => $config->name,
            'relation_id' => $config->osmRelationId,
        ]);

        $geoJson = $this->osmDataProvider->getCountryBoundary($config->osmRelationId);
        $geometryWkt = $this->geometryParser->geoJsonToWkt($geoJson);

        // Используем английское или оригинальное название
        $name = $geoJson['properties']['name:en'] 
            ?? $geoJson['properties']['name'] 
            ?? $config->name;

        return new OsmAreaDto(
            name: $name,
            countryISO3: $config->iso3Code,
            scope: GeoArea::SCOPE['COUNTRY'],
            geometryWkt: $geometryWkt,
            osmId: (string)($geoJson['properties']['osm_id'] ?? $config->osmRelationId),
        );
    }

    /**
     * Парсить города страны
     *
     * @return OsmAreaDto[]
     */
    private function parseCities(CountryConfigDto $config): array
    {
        $this->logger->info('Fetching cities', [
            'country' => $config->name,
            'admin_level' => $config->adminLevelCity,
        ]);

        $citiesGeoJson = $this->osmDataProvider->getCitiesInCountry(
            $config->osmRelationId,
            $config->adminLevelCity
        );

        $cities = [];
        $seenOsmIds = []; // Для дедупликации

        foreach ($citiesGeoJson as $cityGeoJson) {
            try {
                $osmId = (string)($cityGeoJson['properties']['osm_id'] ?? '');
                
                // Пропускаем дубликаты по OSM ID
                if (!empty($osmId) && isset($seenOsmIds[$osmId])) {
                    $this->logger->debug('Skipping duplicate city', [
                        'name' => $cityGeoJson['properties']['name'] ?? 'Unknown',
                        'osm_id' => $osmId,
                    ]);
                    continue;
                }
                
                $geometryWkt = $this->geometryParser->geoJsonToWkt($cityGeoJson);
                
                // Предпочитаем английское название, затем оригинальное
                $name = $cityGeoJson['properties']['name:en'] 
                    ?? $cityGeoJson['properties']['name'] 
                    ?? 'Unknown City';

                $cities[] = new OsmAreaDto(
                    name: $name,
                    countryISO3: $config->iso3Code,
                    scope: GeoArea::SCOPE['CITY'],
                    geometryWkt: $geometryWkt,
                    osmId: $osmId,
                );

                // Отмечаем OSM ID как обработанный
                if (!empty($osmId)) {
                    $seenOsmIds[$osmId] = true;
                }

                $this->logger->debug('City parsed', ['name' => $name, 'osm_id' => $osmId]);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to parse city', [
                    'city_name' => $cityGeoJson['properties']['name'] ?? 'Unknown',
                    'error' => $e->getMessage(),
                ]);
                
                // Продолжаем парсинг остальных городов
                continue;
            }
        }

        return $cities;
    }
}

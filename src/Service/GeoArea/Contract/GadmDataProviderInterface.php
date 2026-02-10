<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Service\GeoArea\Contract;

/**
 * Интерфейс для получения данных из GADM (Global Administrative Areas)
 */
interface GadmDataProviderInterface
{
    /**
     * Получить административные границы для страны и уровня
     *
     * @param string $iso3Code ISO3 код страны (LVA, EST, LTU)
     * @param int $adminLevel Уровень детализации (0=страна, 1-4=регионы)
     * @return array GeoJSON FeatureCollection
     */
    public function getAdminBoundaries(string $iso3Code, int $adminLevel): array;
    
    /**
     * Найти наименьший полигон, содержащий точку
     *
     * @param array $features Массив GeoJSON features
     * @param float $lat Широта
     * @param float $lon Долгота
     * @return array|null GeoJSON feature или null если не найдено
     */
    public function findSmallestContainingPolygon(array $features, float $lat, float $lon): ?array;
}

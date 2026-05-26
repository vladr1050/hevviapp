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

namespace App\Service\GeoArea\Contract;

/**
 * Интерфейс для получения данных из OpenStreetMap
 */
interface OsmDataProviderInterface
{
    /**
     * Получить границы страны по OSM relation ID
     *
     * @param string $relationId ID relation в OSM
     * @return array GeoJSON данные
     */
    public function getCountryBoundary(string $relationId): array;

    /**
     * Получить города страны по OSM relation ID страны
     *
     * @param string $countryRelationId ID relation страны в OSM
     * @param int $adminLevel Уровень административной единицы (обычно 8 или 9 для городов)
     * @return array Массив GeoJSON данных городов
     */
    public function getCitiesInCountry(string $countryRelationId, int $adminLevel = 8): array;

    /**
     * Получить произвольные административные единицы страны по admin_level.
     *
     * В отличие от getCitiesInCountry() здесь не применяется фильтр border_type=city,
     * что позволяет получать все юниты заданного уровня (например novadi, pagasti).
     *
     * @param string $countryRelationId ID relation страны в OSM
     * @param int $adminLevel Уровень административной единицы (например 5 для novadi, 7 для pagasti в Латвии)
     * @param string|null $excludeBorderType Исключить relations с border_type (LV: city для novadi)
     * @return array<int, array> Массив GeoJSON Feature (MultiPolygon)
     */
    public function getAdminUnitsInCountry(string $countryRelationId, int $adminLevel, ?string $excludeBorderType = null): array;
}

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
 * Интерфейс для парсинга геометрии из различных форматов в WKT MULTIPOLYGON
 */
interface GeometryParserInterface
{
    /**
     * Преобразовать GeoJSON геометрию в WKT MULTIPOLYGON
     *
     * @param array $geoJson GeoJSON геометрия
     * @return string WKT представление в формате MULTIPOLYGON
     */
    public function geoJsonToWkt(array $geoJson): string;
}

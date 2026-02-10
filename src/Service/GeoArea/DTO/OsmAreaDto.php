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

namespace App\Service\GeoArea\DTO;

/**
 * DTO для представления OSM области (страны или города)
 */
readonly class OsmAreaDto
{
    public function __construct(
        public string $name,
        public string $countryISO3,
        public int $scope,
        public string $geometryWkt,
        public ?string $osmId = null,
    ) {
    }
}

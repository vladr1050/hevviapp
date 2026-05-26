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
 * DTO для конфигурации страны при парсинге OSM данных
 */
readonly class CountryConfigDto
{
    public function __construct(
        public string $name,
        public string $iso3Code,
        public string $osmRelationId,
        public int $adminLevelCity = 8,
        /** OSM admin_level for municipalities (e.g. novadi in LV → 5, same level as valstspilsētas). */
        public int $adminLevelMunicipality = 5,
        /** OSM admin_level for parishes (e.g. pagasti in LV → 7). */
        public int $adminLevelParish = 7,
        /**
         * When municipalities share admin_level with state cities, exclude relations
         * with this border_type (LV: valstspilsētas use border_type=city at level 5).
         */
        public ?string $municipalityExcludeBorderType = null,
        /** Overpass name regex for municipalities (LV: novads$). */
        public ?string $municipalityNameRegex = null,
    ) {
    }
}

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

namespace App\Service\GeoArea\Config;

use App\Service\GeoArea\DTO\CountryConfigDto;

/**
 * Провайдер конфигураций стран для парсинга OSM данных
 * 
 * Здесь можно легко добавлять новые страны для парсинга
 */
class CountryConfigProvider
{
    /**
     * Список доступных стран для парсинга
     * 
     * OSM Relation IDs можно найти на:
     * - https://www.openstreetmap.org/
     * - Или через Nominatim API: https://nominatim.openstreetmap.org/
     * 
     * @var array<string, array{name: string, iso3: string, osmRelationId: string, adminLevelCity: int}>
     */
    private const COUNTRIES = [
        'latvia' => [
            'name' => 'Latvia',
            'iso3' => 'LVA',
            'osmRelationId' => '72594', // https://www.openstreetmap.org/relation/72594
            'adminLevelCity' => 5, // Государственные города (valstspilsētas)
            // admin_level=5 + border_type="city" дает 7 государственных городов:
            // Rīga, Daugavpils, Liepāja, Jelgava, Jūrmala, Ventspils, Rēzekne
            // Это именно крупные города с цельными polygon boundaries
        ],
        // Добавьте другие страны здесь по мере необходимости:
        // 'estonia' => [
        //     'name' => 'Estonia',
        //     'iso3' => 'EST',
        //     'osmRelationId' => '79510',
        //     'adminLevelCity' => 8,
        // ],
        // 'lithuania' => [
        //     'name' => 'Lithuania',
        //     'iso3' => 'LTU',
        //     'osmRelationId' => '72596',
        //     'adminLevelCity' => 8,
        // ],
    ];

    /**
     * Получить конфигурацию страны по коду
     */
    public function getCountryConfig(string $countryCode): ?CountryConfigDto
    {
        if (!isset(self::COUNTRIES[$countryCode])) {
            return null;
        }

        $config = self::COUNTRIES[$countryCode];

        return new CountryConfigDto(
            name: $config['name'],
            iso3Code: $config['iso3'],
            osmRelationId: $config['osmRelationId'],
            adminLevelCity: $config['adminLevelCity'],
        );
    }

    /**
     * Получить все доступные коды стран
     *
     * @return string[]
     */
    public function getAvailableCountryCodes(): array
    {
        return array_keys(self::COUNTRIES);
    }

    /**
     * Получить конфигурации для нескольких стран
     *
     * @param string[] $countryCodes
     * @return CountryConfigDto[]
     */
    public function getCountriesConfigs(array $countryCodes): array
    {
        $configs = [];

        foreach ($countryCodes as $code) {
            $config = $this->getCountryConfig($code);
            if ($config !== null) {
                $configs[] = $config;
            }
        }

        return $configs;
    }

    /**
     * Получить конфигурации всех доступных стран
     *
     * @return CountryConfigDto[]
     */
    public function getAllCountriesConfigs(): array
    {
        return $this->getCountriesConfigs($this->getAvailableCountryCodes());
    }
}

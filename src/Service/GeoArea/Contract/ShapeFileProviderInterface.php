<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Service\GeoArea\Contract;

/**
 * Интерфейс для работы с Shape файлами (GeoFabrik и другие источники)
 */
interface ShapeFileProviderInterface
{
    /**
     * Скачать и импортировать shape файлы для страны
     *
     * @param string $countryCode Код страны (например, 'latvia')
     * @param string $countryISO3 ISO3 код страны
     * @return array Массив GeoJSON features
     */
    public function importCountryData(string $countryCode, string $countryISO3): array;
    
    /**
     * Проверить доступность shape файлов для страны
     *
     * @param string $countryCode Код страны
     * @return bool
     */
    public function isAvailable(string $countryCode): bool;
}

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

use App\Service\GeoArea\DTO\OsmAreaDto;

/**
 * Интерфейс для генерации дампа данных GeoArea
 */
interface GeoAreaDataDumperInterface
{
    /**
     * Сгенерировать SQL дамп для массива областей
     *
     * @param OsmAreaDto[] $areas Массив областей для дампа
     * @param string $outputPath Путь к файлу для сохранения
     * @return void
     */
    public function generateSqlDump(array $areas, string $outputPath): void;
}

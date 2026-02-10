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

namespace App\Service\GeoArea\DataDumper;

use App\Service\GeoArea\Contract\GeoAreaDataDumperInterface;
use App\Service\GeoArea\DTO\OsmAreaDto;
use Psr\Log\LoggerInterface;

/**
 * Генератор SQL дампа для GeoArea
 */
class GeoAreaSqlDumper implements GeoAreaDataDumperInterface
{
    // Максимальный размер одного файла дампа (1 МБ)
    private const MAX_FILE_SIZE = 1024 * 1024; // 1 МБ в байтах
    
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generateSqlDump(array $areas, string $outputPath): void
    {
        $this->logger->info('Generating SQL dump', [
            'areas_count' => count($areas),
            'output_path' => $outputPath,
        ]);

        // Группируем области по странам
        $areasByCountry = $this->groupAreasByCountry($areas);
        
        $dir = dirname($outputPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException("Failed to create directory: {$dir}");
        }

        // Генерируем дампы для каждой страны
        $generatedFiles = [];
        foreach ($areasByCountry as $countryISO3 => $countryAreas) {
            $files = $this->generateCountryDumps($countryAreas, $countryISO3, $outputPath);
            $generatedFiles = array_merge($generatedFiles, $files);
        }

        $this->logger->info('SQL dumps generated successfully', [
            'files_count' => count($generatedFiles),
            'files' => array_map(fn($f) => basename($f), $generatedFiles),
        ]);
    }

    /**
     * Группировать области по странам
     */
    private function groupAreasByCountry(array $areas): array
    {
        $grouped = [];
        
        foreach ($areas as $area) {
            $iso3 = strtolower($area->countryISO3);
            if (!isset($grouped[$iso3])) {
                $grouped[$iso3] = [];
            }
            $grouped[$iso3][] = $area;
        }
        
        return $grouped;
    }

    /**
     * Сгенерировать дампы для одной страны (может быть несколько файлов)
     *
     * @return string[] Массив путей к созданным файлам
     */
    private function generateCountryDumps(array $areas, string $countryISO3, string $baseOutputPath): array
    {
        $iso3Lower = strtolower($countryISO3);
        $pathInfo = pathinfo($baseOutputPath);
        $dir = $pathInfo['dirname'];
        $baseFilename = $pathInfo['filename'] ?? 'geo_areas_dump';
        
        $partNumber = 1;
        $currentFileContent = [];
        $currentFileSize = 0;
        $generatedFiles = [];
        
        // Заголовок для первого файла
        $header = $this->buildFileHeader($countryISO3, count($areas), $partNumber);
        $currentFileContent[] = $header;
        $currentFileSize += strlen($header);
        
        foreach ($areas as $index => $area) {
            $statement = $this->buildInsertStatement($area) . PHP_EOL;
            $statementSize = strlen($statement);
            
            // Проверяем, не превысит ли добавление этой записи лимит
            if ($currentFileSize + $statementSize > self::MAX_FILE_SIZE && !empty($currentFileContent)) {
                // Сохраняем текущий файл
                $filename = $this->buildFilename($dir, $baseFilename, $iso3Lower, $partNumber);
                $this->writeFile($filename, implode('', $currentFileContent));
                $generatedFiles[] = $filename;
                
                $this->logger->info('Generated dump part', [
                    'country' => $countryISO3,
                    'part' => $partNumber,
                    'size' => $currentFileSize,
                    'areas' => count(array_filter($currentFileContent, fn($line) => str_starts_with($line, 'INSERT'))),
                ]);
                
                // Начинаем новый файл
                $partNumber++;
                $currentFileContent = [];
                $currentFileSize = 0;
                
                // Заголовок для нового файла
                $header = $this->buildFileHeader($countryISO3, count($areas), $partNumber);
                $currentFileContent[] = $header;
                $currentFileSize += strlen($header);
            }
            
            // Добавляем запись в текущий файл
            $currentFileContent[] = $statement;
            $currentFileSize += $statementSize;
        }
        
        // Сохраняем последний файл, если есть незаписанные данные
        if (!empty($currentFileContent)) {
            $filename = $this->buildFilename($dir, $baseFilename, $iso3Lower, $partNumber);
            $this->writeFile($filename, implode('', $currentFileContent));
            $generatedFiles[] = $filename;
            
            $this->logger->info('Generated dump part', [
                'country' => $countryISO3,
                'part' => $partNumber,
                'size' => $currentFileSize,
                'areas' => count(array_filter($currentFileContent, fn($line) => str_starts_with($line, 'INSERT'))),
            ]);
        }
        
        return $generatedFiles;
    }

    /**
     * Построить имя файла с номером части
     */
    private function buildFilename(string $dir, string $baseFilename, string $iso3Lower, int $partNumber): string
    {
        return sprintf(
            '%s/%s_%s_%02d.sql',
            $dir,
            $baseFilename,
            $iso3Lower,
            $partNumber
        );
    }

    /**
     * Записать содержимое в файл
     */
    private function writeFile(string $filename, string $content): void
    {
        if (file_put_contents($filename, $content) === false) {
            throw new \RuntimeException("Failed to write SQL dump to: {$filename}");
        }
    }

    /**
     * Построить заголовок для файла дампа
     */
    private function buildFileHeader(string $countryISO3, int $totalAreas, int $partNumber): string
    {
        $lines = [];
        $lines[] = '-- GeoArea SQL Dump';
        $lines[] = '-- Country: ' . $countryISO3;
        $lines[] = '-- Part: ' . $partNumber;
        $lines[] = '-- Generated at: ' . date('Y-m-d H:i:s');
        $lines[] = '-- Total areas in country: ' . $totalAreas;
        $lines[] = '-- Max file size: ' . (self::MAX_FILE_SIZE / 1024 / 1024) . ' MB';
        $lines[] = '';
        
        // Очистка существующих данных (только в первой части)
        if ($partNumber === 1) {
            $lines[] = "-- TRUNCATE TABLE geo_area WHERE country_iso3 = '{$countryISO3}' CASCADE;";
            $lines[] = '';
        }
        
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }


    /**
     * Построить INSERT statement для области
     */
    private function buildInsertStatement(OsmAreaDto $area): string
    {
        $name = $this->escapeSqlString($area->name);
        $countryISO3 = $this->escapeSqlString($area->countryISO3);
        $geometry = $this->escapeSqlString($area->geometryWkt);
        $uuid = $this->generateUuid();
        $now = date('Y-m-d H:i:s');

        return sprintf(
            "INSERT INTO geo_area (id, name, scope, geometry, country_iso3, created_at, updated_at) VALUES ('%s', '%s', %d, ST_Multi(ST_Buffer(ST_GeomFromText('%s', 4326), 0)), '%s', '%s', '%s');",
            $uuid,
            $name,
            $area->scope,
            $geometry,
            $countryISO3,
            $now,
            $now
        );
    }

    /**
     * Экранировать строку для SQL
     */
    private function escapeSqlString(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    /**
     * Сгенерировать UUID v7
     */
    private function generateUuid(): string
    {
        // Генерация UUID v7 (time-ordered)
        // Symfony Uid используется в сущности, но для дампа сгенерируем простой UUID v4
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}

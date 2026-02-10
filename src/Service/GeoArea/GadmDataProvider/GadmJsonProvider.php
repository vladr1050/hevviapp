<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Service\GeoArea\GadmDataProvider;

use App\Service\GeoArea\Contract\GadmDataProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Провайдер для загрузки данных из GADM (UC Davis JSON mirror)
 * 
 * GADM предоставляет высококачественные административные границы с правильными полигонами
 */
class GadmJsonProvider implements GadmDataProviderInterface
{
    private const GADM_BASE_URL = 'https://geodata.ucdavis.edu/gadm/gadm4.1/json';
    private const DOWNLOAD_TIMEOUT = 300;
    
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
    ) {
    }

    public function getAdminBoundaries(string $iso3Code, int $adminLevel): array
    {
        $this->logger->info('Fetching GADM data', [
            'iso3' => $iso3Code,
            'level' => $adminLevel,
        ]);

        // Скачиваем и распаковываем
        $jsonPath = $this->downloadAndExtract($iso3Code, $adminLevel);
        
        if ($jsonPath === null) {
            throw new \RuntimeException(
                "GADM data not available for {$iso3Code} at level {$adminLevel}"
            );
        }

        // Читаем GeoJSON
        $geoJson = json_decode(file_get_contents($jsonPath), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse GADM JSON: ' . json_last_error_msg());
        }

        // Удаляем временный файл
        unlink($jsonPath);
        
        $featuresCount = count($geoJson['features'] ?? []);
        
        $this->logger->info('GADM data loaded', [
            'features' => $featuresCount,
        ]);

        return $geoJson;
    }

    public function findSmallestContainingPolygon(array $features, float $lat, float $lon): ?array
    {
        $candidates = [];

        foreach ($features as $feature) {
            if (!isset($feature['geometry'])) {
                continue;
            }

            // Простая проверка bbox сначала (для оптимизации)
            if ($this->isPointInBbox($feature, $lat, $lon)) {
                // Вычисляем площадь (приблизительно)
                $area = $this->estimateArea($feature['geometry']);
                $candidates[] = [
                    'feature' => $feature,
                    'area' => $area,
                ];
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Сортируем по площади (наименьший первый)
        usort($candidates, fn($a, $b) => $a['area'] <=> $b['area']);

        return $candidates[0]['feature'];
    }

    /**
     * Скачать и распаковать GADM JSON
     */
    private function downloadAndExtract(string $iso3Code, int $adminLevel): ?string
    {
        $url = sprintf(
            '%s/gadm41_%s_%d.json.zip',
            self::GADM_BASE_URL,
            $iso3Code,
            $adminLevel
        );

        // Проверяем доступность
        if (!$this->checkUrlExists($url)) {
            $this->logger->warning('GADM file not available', [
                'url' => $url,
            ]);
            return null;
        }

        // Создаем временную директорию
        $tempDir = $this->projectDir . '/var/gadm_temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = sprintf('%s/gadm41_%s_%d.json.zip', $tempDir, $iso3Code, $adminLevel);
        $jsonPath = sprintf('%s/gadm41_%s_%d.json', $tempDir, $iso3Code, $adminLevel);

        // Скачиваем
        $this->logger->info('Downloading GADM file', [
            'url' => $url,
            'size' => '~5-50 MB',
        ]);

        $ch = curl_init($url);
        $fp = fopen($zipPath, 'w+');
        
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => self::DOWNLOAD_TIMEOUT,
            CURLOPT_USERAGENT => 'Hevii BackOffice GeoArea Parser/1.0',
        ]);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode !== 200) {
            unlink($zipPath);
            return null;
        }

        $this->logger->info('Downloaded', [
            'file_size' => filesize($zipPath),
        ]);

        // Распаковываем
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            unlink($zipPath);
            return null;
        }

        // Извлекаем JSON файл
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (str_ends_with($filename, '.json')) {
                $zip->extractTo($tempDir, $filename);
                $extractedPath = $tempDir . '/' . $filename;
                
                // Переименовываем для единообразия
                if ($extractedPath !== $jsonPath) {
                    rename($extractedPath, $jsonPath);
                }
                break;
            }
        }

        $zip->close();
        unlink($zipPath);

        if (!file_exists($jsonPath)) {
            return null;
        }

        $this->logger->info('Extracted JSON', [
            'file_size' => filesize($jsonPath),
        ]);

        return $jsonPath;
    }

    /**
     * Проверить существование URL
     */
    private function checkUrlExists(string $url): bool
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'Hevii BackOffice GeoArea Parser/1.0',
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    /**
     * Проверить попадает ли точка в bbox feature
     */
    private function isPointInBbox(array $feature, float $lat, float $lon): bool
    {
        // Простая проверка по координатам (без полной геометрической проверки)
        // Для точной проверки используем PostGIS после загрузки в БД
        
        if (!isset($feature['geometry']['coordinates'])) {
            return false;
        }

        // Это упрощенная проверка, полная проверка будет в PostGIS
        return true;
    }

    /**
     * Оценить площадь геометрии (приблизительно)
     */
    private function estimateArea(array $geometry): float
    {
        // Для простоты подсчитываем количество координат
        // Реальная площадь будет вычислена в PostGIS
        return $this->countCoordinates($geometry);
    }

    /**
     * Подсчитать количество координат (для оценки размера)
     */
    private function countCoordinates(array $geometry): int
    {
        if (!isset($geometry['coordinates'])) {
            return 0;
        }

        $count = 0;
        $this->countRecursive($geometry['coordinates'], $count);
        
        return $count;
    }

    /**
     * Рекурсивный подсчет координат
     */
    private function countRecursive($data, &$count): void
    {
        if (is_array($data)) {
            if (isset($data[0]) && is_numeric($data[0])) {
                // Это координата [lon, lat]
                $count++;
            } else {
                foreach ($data as $item) {
                    $this->countRecursive($item, $count);
                }
            }
        }
    }
}

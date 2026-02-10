<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Service\GeoArea\ShapeFileProvider;

use App\Entity\GeoArea;
use App\Service\GeoArea\Contract\ShapeFileProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Провайдер для загрузки данных из GeoFabrik
 * 
 * GeoFabrik предоставляет готовые shape files с правильными полигонами
 */
class GeoFabrikProvider implements ShapeFileProviderInterface
{
    private const GEOFABRIK_BASE_URL = 'https://download.geofabrik.de/europe';
    
    private const COUNTRY_MAPPING = [
        'latvia' => [
            'url' => 'latvia-latest-free.shp.zip',
            'name_en' => 'Latvia',
        ],
        'estonia' => [
            'url' => 'estonia-latest-free.shp.zip',
            'name_en' => 'Estonia',
        ],
        'lithuania' => [
            'url' => 'lithuania-latest-free.shp.zip',
            'name_en' => 'Lithuania',
        ],
    ];
    
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
    ) {
    }

    public function isAvailable(string $countryCode): bool
    {
        return isset(self::COUNTRY_MAPPING[strtolower($countryCode)]);
    }

    public function importCountryData(string $countryCode, string $countryISO3): array
    {
        $countryCode = strtolower($countryCode);
        
        if (!$this->isAvailable($countryCode)) {
            throw new \InvalidArgumentException("Country '{$countryCode}' not available in GeoFabrik");
        }

        $this->logger->info('Starting GeoFabrik import', [
            'country' => $countryCode,
            'iso3' => $countryISO3,
        ]);

        // 1. Скачать shape файлы
        $downloadPath = $this->downloadShapeFiles($countryCode);
        
        // 2. Распаковать
        $extractPath = $this->extractShapeFiles($downloadPath);
        
        // 3. Импортировать в PostGIS
        $this->importToPostGIS($extractPath, $countryISO3);
        
        // 4. Экспортировать в GeoJSON
        $areas = $this->exportToGeoJson($countryISO3);
        
        // 5. Очистить временные файлы
        $this->cleanup($downloadPath, $extractPath);
        
        $this->logger->info('GeoFabrik import completed', [
            'country' => $countryCode,
            'areas_count' => count($areas),
        ]);
        
        return $areas;
    }

    /**
     * Скачать shape файлы
     */
    private function downloadShapeFiles(string $countryCode): string
    {
        $config = self::COUNTRY_MAPPING[$countryCode];
        $url = self::GEOFABRIK_BASE_URL . '/' . $config['url'];
        $downloadPath = $this->projectDir . '/var/geofabrik/' . $config['url'];
        
        $this->logger->info('Downloading shape files', [
            'url' => $url,
            'destination' => $downloadPath,
        ]);

        // Создаем директорию
        $dir = dirname($downloadPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException("Failed to create directory: {$dir}");
        }

        // Скачиваем файл
        $ch = curl_init($url);
        $fp = fopen($downloadPath, 'w+');
        
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_USERAGENT => 'Hevii BackOffice GeoArea Parser/1.0',
        ]);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode !== 200) {
            throw new \RuntimeException("Failed to download shape files. HTTP {$httpCode}");
        }

        $this->logger->info('Downloaded successfully', [
            'file_size' => filesize($downloadPath),
        ]);

        return $downloadPath;
    }

    /**
     * Распаковать shape файлы
     */
    private function extractShapeFiles(string $zipPath): string
    {
        $extractPath = dirname($zipPath) . '/' . pathinfo($zipPath, PATHINFO_FILENAME);
        
        if (!is_dir($extractPath) && !mkdir($extractPath, 0755, true)) {
            throw new \RuntimeException("Failed to create extract directory");
        }

        $this->logger->info('Extracting shape files', [
            'zip' => $zipPath,
            'destination' => $extractPath,
        ]);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("Failed to open ZIP file");
        }

        $zip->extractTo($extractPath);
        $zip->close();

        $this->logger->info('Extracted successfully');

        return $extractPath;
    }

    /**
     * Импортировать shape файлы в PostGIS во временную таблицу
     */
    private function importToPostGIS(string $extractPath, string $countryISO3): void
    {
        $this->logger->info('Importing to PostGIS', [
            'path' => $extractPath,
        ]);

        // Импортируем административные границы (places)
        // GeoFabrik содержит файл gis_osm_places_free_1.shp с полигонами городов
        $shapefile = $extractPath . '/gis_osm_places_free_1.shp';
        
        if (!file_exists($shapefile)) {
            throw new \RuntimeException("Shape file not found: {$shapefile}");
        }

        // Используем shp2pgsql для импорта
        $tempTable = 'temp_geofabrik_places_' . strtolower($countryISO3);
        
        $command = sprintf(
            'shp2pgsql -s 4326 -I -d %s %s | psql "$DATABASE_URL" 2>&1',
            escapeshellarg($shapefile),
            escapeshellarg($tempTable)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Failed to import shapefile: ' . implode("\n", $output));
        }

        $this->logger->info('Imported to temp table', [
            'table' => $tempTable,
        ]);
    }

    /**
     * Экспортировать данные из временной таблицы в GeoJSON
     */
    private function exportToGeoJson(string $countryISO3): array
    {
        $tempTable = 'temp_geofabrik_places_' . strtolower($countryISO3);
        
        // TODO: Выполнить SQL запрос для экспорта
        // Вернуть массив GeoJSON features
        
        return [];
    }

    /**
     * Очистить временные файлы
     */
    private function cleanup(string $downloadPath, string $extractPath): void
    {
        // Удаляем скачанный ZIP
        if (file_exists($downloadPath)) {
            unlink($downloadPath);
        }
        
        // Удаляем распакованные файлы
        if (is_dir($extractPath)) {
            $this->removeDirectory($extractPath);
        }
        
        $this->logger->info('Cleanup completed');
    }

    /**
     * Рекурсивно удалить директорию
     */
    private function removeDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }
}

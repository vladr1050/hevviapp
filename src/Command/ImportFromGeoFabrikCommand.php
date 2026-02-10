<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Command;

use App\Entity\GeoArea;
use App\Service\GeoArea\Config\CountryConfigProvider;
use App\Service\GeoArea\Contract\GeoAreaDataDumperInterface;
use App\Service\GeoArea\DTO\OsmAreaDto;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда для импорта GeoArea из GeoFabrik shape files
 */
#[AsCommand(
    name: 'app:import-from-geofabrik',
    description: 'Import geo areas from GeoFabrik shape files (better quality than OSM API)',
)]
class ImportFromGeoFabrikCommand extends Command
{
    private const GEOFABRIK_BASE_URL = 'https://download.geofabrik.de/europe';
    
    private const COUNTRY_URLS = [
        'latvia' => 'latvia-latest-free.shp.zip',
        'estonia' => 'estonia-latest-free.shp.zip',
        'lithuania' => 'lithuania-latest-free.shp.zip',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly CountryConfigProvider $countryConfigProvider,
        private readonly GeoAreaDataDumperInterface $dataDumper,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'country',
                InputArgument::REQUIRED,
                'Country code (latvia, estonia, lithuania)',
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output file path for SQL dump',
                'docker/dumps/geo_areas/geo_areas_dump.sql',
            )
            ->addOption(
                'keep-temp',
                null,
                InputOption::VALUE_NONE,
                'Keep temporary files after import',
            )
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command imports geo areas from GeoFabrik shape files.

This method provides better quality polygons than Overpass API:
  - ✅ Solid filled polygons (not just outlines)
  - ✅ Proper administrative boundaries  
  - ✅ Faster download
  - ✅ No API timeouts

Examples:

Import Latvia:
  <info>php %command.full_name% latvia</info>

Import Estonia:
  <info>php %command.full_name% estonia</info>

Keep temporary files:
  <info>php %command.full_name% latvia --keep-temp</info>

Requirements:
  - ogr2ogr (GDAL) must be installed in Docker container
  - Internet connection
  - PostgreSQL with PostGIS
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('GeoFabrik Import для GeoArea');

        $countryCode = strtolower($input->getArgument('country'));
        
        // Проверка доступности страны
        if (!isset(self::COUNTRY_URLS[$countryCode])) {
            $io->error(sprintf(
                'Country "%s" not available. Available: %s',
                $countryCode,
                implode(', ', array_keys(self::COUNTRY_URLS))
            ));
            return Command::INVALID;
        }

        // Получаем конфигурацию страны
        $config = $this->countryConfigProvider->getCountryConfig($countryCode);
        if ($config === null) {
            $io->error("Country configuration not found for: {$countryCode}");
            return Command::FAILURE;
        }

        $io->info(sprintf(
            'Importing: %s (%s)',
            $config->name,
            $config->iso3Code
        ));

        $io->section('Step 1: Downloading shape files from GeoFabrik');
        
        try {
            $downloadPath = $this->downloadFile($countryCode, $io);
            $io->success('Downloaded: ' . basename($downloadPath));
            
            $io->section('Step 2: Extracting ZIP archive');
            $extractPath = $this->extractZip($downloadPath, $io);
            $io->success('Extracted to: ' . $extractPath);
            
            $io->section('Step 3: Finding shape files');
            $shapefiles = $this->findShapeFiles($extractPath);
            $io->listing(array_map('basename', $shapefiles));
            
            $io->section('Step 4: Importing boundaries to database');
            $areas = $this->importBoundaries($shapefiles, $config, $io);
            $io->success(sprintf('Imported %d areas', count($areas)));
            
            $io->section('Step 5: Generating SQL dump');
            $outputPath = $input->getOption('output');
            if (!str_starts_with($outputPath, '/')) {
                $outputPath = $this->projectDir . '/' . $outputPath;
            }
            
            $this->dataDumper->generateSqlDump($areas, $outputPath);
            $io->success('SQL dump saved to: ' . $outputPath);
            
            // Cleanup
            if (!$input->getOption('keep-temp')) {
                $io->section('Step 6: Cleaning up');
                $this->cleanup($downloadPath, $extractPath);
                $io->success('Temporary files removed');
            }
            
            $io->success([
                'Import completed successfully!',
                sprintf('Country: %s', $config->name),
                sprintf('Total areas: %d', count($areas)),
                sprintf('SQL dump: %s', basename($outputPath)),
            ]);

            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error([
                'Import failed!',
                'Error: ' . $e->getMessage(),
            ]);

            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function downloadFile(string $countryCode, SymfonyStyle $io): string
    {
        $url = self::GEOFABRIK_BASE_URL . '/' . self::COUNTRY_URLS[$countryCode];
        $downloadPath = $this->projectDir . '/var/geofabrik/' . self::COUNTRY_URLS[$countryCode];
        
        // Создаем директорию
        $dir = dirname($downloadPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $io->text('Downloading from: ' . $url);
        $io->text('File size: ~110-130 MB (this may take 1-2 minutes)');

        // Используем curl для скачивания с прогрессом
        $ch = curl_init($url);
        $fp = fopen($downloadPath, 'w+');
        
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_USERAGENT => 'Hevii BackOffice GeoArea Parser/1.0',
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function($resource, $download_size, $downloaded, $upload_size, $uploaded) use ($io) {
                if ($download_size > 0) {
                    $percent = ($downloaded / $download_size) * 100;
                    if ($percent > 0 && $percent % 10 < 1) {
                        $io->write('.');
                    }
                }
            },
        ]);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode !== 200) {
            throw new \RuntimeException("Failed to download. HTTP {$httpCode}");
        }

        return $downloadPath;
    }

    private function extractZip(string $zipPath, SymfonyStyle $io): string
    {
        $extractPath = dirname($zipPath) . '/' . pathinfo($zipPath, PATHINFO_FILENAME);
        
        if (is_dir($extractPath)) {
            // Удаляем старые данные
            $this->removeDirectory($extractPath);
        }
        
        mkdir($extractPath, 0755, true);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("Failed to open ZIP file");
        }

        $io->text(sprintf('Extracting %d files...', $zip->numFiles));
        $zip->extractTo($extractPath);
        $zip->close();

        return $extractPath;
    }

    private function findShapeFiles(string $dir): array
    {
        // Ищем файлы, которые нам нужны
        $needed = [
            'gis_osm_places_free_1.shp' => 'Cities and towns (polygons)',
            'gis_osm_adminareas_free_1.shp' => 'Administrative areas (alternative)',
        ];

        $found = [];
        foreach ($needed as $filename => $description) {
            $path = $dir . '/' . $filename;
            if (file_exists($path)) {
                $found[$path] = $description;
            }
        }

        if (empty($found)) {
            throw new \RuntimeException('No suitable shape files found in archive');
        }

        return $found;
    }

    private function importBoundaries(array $shapefiles, $config, SymfonyStyle $io): array
    {
        $areas = [];
        
        // 1. Добавляем страну
        $io->text('Adding country boundary...');
        $countryArea = $this->getCountryBoundary($config);
        if ($countryArea !== null) {
            $areas[] = $countryArea;
            $io->text('✓ Country added');
        }

        // 2. Импортируем города из shapefiles
        foreach ($shapefiles as $shapefile => $description) {
            $io->text("Processing: {$description}");
            
            $tempTable = 'temp_import_' . md5($shapefile);
            
            // Импортируем через ogr2ogr (более надежно чем shp2pgsql)
            $importCmd = sprintf(
                'ogr2ogr -f PostgreSQL PG:"$DATABASE_URL" %s -nln %s -overwrite -t_srid 4326 -lco GEOMETRY_NAME=geom 2>&1',
                escapeshellarg($shapefile),
                escapeshellarg($tempTable)
            );

            exec($importCmd, $output, $returnCode);

            if ($returnCode !== 0) {
                $io->warning('Failed to import ' . basename($shapefile));
                continue;
            }

            // Читаем данные из временной таблицы
            $cityAreas = $this->readFromTempTable($tempTable, $config->iso3Code, $io);
            $areas = array_merge($areas, $cityAreas);
            
            // Удаляем временную таблицу
            $this->connection->executeStatement("DROP TABLE IF EXISTS {$tempTable} CASCADE");
            
            $io->text(sprintf('✓ Imported %d cities', count($cityAreas)));
        }

        return $areas;
    }

    private function getCountryBoundary($config): ?OsmAreaDto
    {
        // Получаем границу страны из БД или OSM API
        // Для простоты можно использовать существующий OsmDataProvider
        return null; // TODO: Реализовать
    }

    private function readFromTempTable(string $tableName, string $countryISO3, SymfonyStyle $io): array
    {
        $areas = [];
        
        // Читаем данные и конвертируем в OsmAreaDto
        $query = "
            SELECT 
                name,
                fclass,
                ST_AsText(ST_Multi(ST_MakeValid(geom))) as geometry_wkt,
                ST_GeometryType(geom) as orig_type
            FROM {$tableName}
            WHERE fclass IN ('city', 'town', 'village')
            AND ST_IsValid(geom)
            AND geom IS NOT NULL
        ";

        try {
            $results = $this->connection->fetchAllAssociative($query);
            
            foreach ($results as $row) {
                // Фильтруем только города
                if (!in_array($row['fclass'], ['city', 'town'])) {
                    continue;
                }

                $areas[] = new OsmAreaDto(
                    name: $row['name'] ?? 'Unknown',
                    countryISO3: $countryISO3,
                    scope: GeoArea::SCOPE['CITY'],
                    geometryWkt: $row['geometry_wkt'],
                    osmId: null,
                );
            }
        } catch (\Exception $e) {
            $io->warning('Failed to read from temp table: ' . $e->getMessage());
        }

        return $areas;
    }

    private function cleanup(string $downloadPath, string $extractPath): void
    {
        if (file_exists($downloadPath)) {
            unlink($downloadPath);
        }
        
        if (is_dir($extractPath)) {
            $this->removeDirectory($extractPath);
        }
    }

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

<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Command;

use App\Service\GeoArea\Config\CountryConfigProvider;
use App\Service\GeoArea\Contract\GeoAreaDataDumperInterface;
use App\Service\GeoArea\GadmGeoAreaParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда для парсинга GeoArea из GADM (лучшее качество полигонов!)
 */
#[AsCommand(
    name: 'app:parse-geo-areas-gadm',
    description: 'Parse geo areas from GADM - BEST QUALITY polygons!',
)]
class ParseGeoAreasGadmCommand extends Command
{
    // Вместо фиксированного списка будем загружать все города из Nominatim/OSM

    public function __construct(
        private readonly GadmGeoAreaParser $gadmParser,
        private readonly GeoAreaDataDumperInterface $dataDumper,
        private readonly CountryConfigProvider $countryConfigProvider,
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
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command parses geo areas from GADM database.

GADM provides HIGH QUALITY administrative boundaries:
  - ✅ Proper filled polygons (not outlines)
  - ✅ Precise administrative boundaries
  - ✅ Best quality available
  - ✅ Used by professionals worldwide

Examples:

Parse Latvia:
  <info>php %command.full_name% latvia</info>

Parse Estonia:
  <info>php %command.full_name% estonia</info>

Custom output:
  <info>php %command.full_name% latvia -o docker/dumps/geo_areas/custom.sql</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('GeoArea Parser from GADM (High Quality)');

        $countryCode = strtolower($input->getArgument('country'));
        
        // Получаем конфигурацию
        $config = $this->countryConfigProvider->getCountryConfig($countryCode);
        if ($config === null) {
            $io->error("Country configuration not found for: {$countryCode}");
            $io->note('Available: ' . implode(', ', $this->countryConfigProvider->getAvailableCountryCodes()));
            return Command::FAILURE;
        }

        $io->section('Configuration');
        $io->definitionList(
            ['Country' => $config->name],
            ['ISO3' => $config->iso3Code],
            ['Admin Level' => 'ADM2 (municipalities/counties)'],
            ['Source' => 'GADM 4.1 (UC Davis)'],
        );
        
        $io->note('Will load all administrative units at ADM2 level as cities');

        if (!$io->confirm('Start parsing from GADM?', true)) {
            return Command::SUCCESS;
        }

        try {
            $io->section('Parsing from GADM');
            $io->note('This will download ~5-50 MB and may take 2-3 minutes');

            // Парсинг всех административных единиц
            $areas = $this->gadmParser->parseCountryWithAllCities($config->iso3Code, 2);
            
            $io->newLine();

            $io->success(sprintf(
                'Parsed %d areas (1 country + %d cities)',
                count($areas),
                count($areas) - 1
            ));

            // Генерация дампа
            $io->section('Generating SQL dump');
            
            $outputPath = $input->getOption('output');
            if (!str_starts_with($outputPath, '/')) {
                $outputPath = $this->projectDir . '/' . $outputPath;
            }

            $this->dataDumper->generateSqlDump($areas, $outputPath);

            $io->success([
                'GADM import completed successfully!',
                sprintf('Total areas: %d', count($areas)),
                sprintf('SQL dump: %s', $outputPath),
                '',
                '✨ High quality filled polygons ready!',
            ]);

            $io->note([
                'To load into database:',
                '  docker-compose exec database psql -U app -d app -f ' . basename($outputPath),
                '',
                'Or restart container for auto-load:',
                '  docker-compose restart',
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error([
                'Parsing failed!',
                'Error: ' . $e->getMessage(),
            ]);

            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Загрузить список городов из Nominatim API
     */
    private function loadCitiesFromNominatim(string $countryName, string $iso3Code, SymfonyStyle $io): array
    {
        $cities = [];
        
        $io->text('Fetching cities and towns from Nominatim...');
        
        // Загружаем города (place=city)
        $citiesData = $this->fetchFromNominatim($countryName, 'city');
        foreach ($citiesData as $city) {
            if (isset($city['lat'], $city['lon'], $city['display_name'])) {
                $name = $this->extractCityName($city['display_name']);
                $cities[] = [
                    'name' => $name,
                    'lat' => (float)$city['lat'],
                    'lon' => (float)$city['lon'],
                    'type' => 'city',
                ];
            }
        }
        
        $io->text(sprintf('  Found %d cities', count($cities)));
        
        // Загружаем towns (place=town)
        $townsData = $this->fetchFromNominatim($countryName, 'town');
        foreach ($townsData as $town) {
            if (isset($town['lat'], $town['lon'], $town['display_name'])) {
                $name = $this->extractCityName($town['display_name']);
                // Избегаем дубликатов
                $exists = false;
                foreach ($cities as $city) {
                    if ($city['name'] === $name) {
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    $cities[] = [
                        'name' => $name,
                        'lat' => (float)$town['lat'],
                        'lon' => (float)$town['lon'],
                        'type' => 'town',
                    ];
                }
            }
        }
        
        $io->text(sprintf('  Found %d towns (total: %d)', count($townsData), count($cities)));
        
        return $cities;
    }

    /**
     * Получить данные из Nominatim
     */
    private function fetchFromNominatim(string $country, string $placeType): array
    {
        $url = sprintf(
            'https://nominatim.openstreetmap.org/search?country=%s&featureType=%s&format=json&limit=50',
            urlencode($country),
            urlencode($placeType)
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Hevii BackOffice GeoArea Parser/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            return [];
        }

        $data = json_decode($response, true);
        
        return is_array($data) ? $data : [];
    }

    /**
     * Извлечь название города из display_name
     */
    private function extractCityName(string $displayName): string
    {
        // display_name формата: "Riga, LV-RIX, Latvia"
        $parts = explode(',', $displayName);
        return trim($parts[0]);
    }
}

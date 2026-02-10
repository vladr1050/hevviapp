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

namespace App\Command;

use App\Service\GeoArea\Config\CountryConfigProvider;
use App\Service\GeoArea\Contract\GeoAreaDataDumperInterface;
use App\Service\GeoArea\GeoAreaParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда для парсинга GeoArea из OpenStreetMap
 */
#[AsCommand(
    name: 'app:parse-geo-areas',
    description: 'Parse geo areas (countries and cities) from OpenStreetMap',
)]
class ParseGeoAreasCommand extends Command
{
    public function __construct(
        private readonly GeoAreaParser $geoAreaParser,
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
                'countries',
                InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'Country codes to parse (e.g., latvia, estonia). Leave empty to parse all available countries.',
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
The <info>%command.name%</info> command parses geo areas from OpenStreetMap.

Examples:

Parse Latvia only:
  <info>php %command.full_name% latvia</info>

Parse multiple countries:
  <info>php %command.full_name% latvia estonia lithuania</info>

Parse all available countries:
  <info>php %command.full_name%</info>

Specify custom output directory:
  <info>php %command.full_name% latvia -o docker/dumps/geo_areas/custom_dump.sql</info>
  
Note: Dumps are automatically saved to docker/dumps/geo_areas/ directory

Available countries:
  - latvia (LVA) - Latvia

To add more countries, edit CountryConfigProvider::COUNTRIES
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('GeoArea Parser from OpenStreetMap');

        // Получаем список стран для парсинга
        $requestedCountries = $input->getArgument('countries');
        
        if (empty($requestedCountries)) {
            $requestedCountries = $this->countryConfigProvider->getAvailableCountryCodes();
            $io->note('No countries specified, parsing all available countries');
        }

        // Валидация стран
        $availableCountries = $this->countryConfigProvider->getAvailableCountryCodes();
        $invalidCountries = array_diff($requestedCountries, $availableCountries);
        
        if (!empty($invalidCountries)) {
            $io->error(sprintf(
                'Invalid country codes: %s. Available: %s',
                implode(', ', $invalidCountries),
                implode(', ', $availableCountries)
            ));
            
            return Command::INVALID;
        }

        // Получаем конфигурации стран
        $countriesConfigs = $this->countryConfigProvider->getCountriesConfigs($requestedCountries);
        
        if (empty($countriesConfigs)) {
            $io->error('No valid countries found to parse');
            return Command::FAILURE;
        }

        $io->section('Countries to parse:');
        $io->listing(array_map(
            fn($config) => sprintf('%s (%s) - OSM Relation: %s', $config->name, $config->iso3Code, $config->osmRelationId),
            $countriesConfigs
        ));

        // Определяем путь к выходному файлу
        $outputPath = $input->getOption('output');
        if (!str_starts_with($outputPath, '/')) {
            $outputPath = $this->projectDir . '/' . $outputPath;
        }

        $io->info('Output file: ' . $outputPath);
        
        // Подтверждение
        if (!$io->confirm('Start parsing?', true)) {
            $io->warning('Parsing cancelled');
            return Command::SUCCESS;
        }

        // Парсинг
        $io->section('Parsing data from OpenStreetMap...');
        $io->note('This may take several minutes depending on the number of countries and cities');

        try {
            $progressBar = $io->createProgressBar(count($countriesConfigs));
            $progressBar->start();

            $allAreas = [];
            foreach ($countriesConfigs as $config) {
                $io->writeln('');
                $io->info(sprintf('Parsing %s...', $config->name));
                
                $areas = $this->geoAreaParser->parseCountry($config);
                $allAreas = array_merge($allAreas, $areas);
                
                $io->success(sprintf(
                    '%s parsed: %d areas (1 country + %d cities)',
                    $config->name,
                    count($areas),
                    count($areas) - 1
                ));
                
                $progressBar->advance();
            }

            $progressBar->finish();
            $io->writeln('');

            // Генерация дампа из уже распарсенных данных
            $io->section('Generating SQL dump...');
            
            // Используем dataDumper напрямую, так как данные уже распарсены в цикле выше
            $this->dataDumper->generateSqlDump($allAreas, $outputPath);

            $io->success([
                'Parsing completed successfully!',
                sprintf('Total areas parsed: %d', count($allAreas)),
                sprintf('SQL dump saved to: %s', $outputPath),
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
}

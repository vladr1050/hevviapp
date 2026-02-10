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
use App\Service\GeoArea\Contract\OsmDataProviderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда для тестирования подключения к OSM Overpass API
 */
#[AsCommand(
    name: 'app:test-osm-connection',
    description: 'Test connection to OpenStreetMap Overpass API',
)]
class TestOsmConnectionCommand extends Command
{
    public function __construct(
        private readonly OsmDataProviderInterface $osmDataProvider,
        private readonly CountryConfigProvider $countryConfigProvider,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Testing OSM Overpass API Connection');

        // 1. Проверка доступности API
        $io->section('1. Checking Overpass API availability');
        
        if (!$this->checkApiAvailability()) {
            $io->error('Overpass API is not available. Please try again later.');
            return Command::FAILURE;
        }
        
        $io->success('Overpass API is available');

        // 2. Тест загрузки границ Латвии
        $io->section('2. Testing Latvia boundary fetch');
        
        $latviaConfig = $this->countryConfigProvider->getCountryConfig('latvia');
        
        if ($latviaConfig === null) {
            $io->error('Latvia config not found');
            return Command::FAILURE;
        }

        try {
            $io->text(sprintf('Fetching data for relation: %s', $latviaConfig->osmRelationId));
            
            $countryData = $this->osmDataProvider->getCountryBoundary($latviaConfig->osmRelationId);
            
            $io->success('Successfully fetched Latvia boundary');
            $io->table(
                ['Property', 'Value'],
                [
                    ['Name', $countryData['properties']['name'] ?? 'N/A'],
                    ['Name (EN)', $countryData['properties']['name:en'] ?? 'N/A'],
                    ['Name (RU)', $countryData['properties']['name:ru'] ?? 'N/A'],
                    ['Admin Level', $countryData['properties']['admin_level'] ?? 'N/A'],
                    ['OSM ID', $countryData['properties']['osm_id'] ?? 'N/A'],
                    ['Geometry Type', $countryData['geometry']['type'] ?? 'N/A'],
                ]
            );
        } catch (\Exception $e) {
            $io->error([
                'Failed to fetch Latvia boundary',
                'Error: ' . $e->getMessage(),
            ]);
            return Command::FAILURE;
        }

        // 3. Тест загрузки городов (только первые 3 для скорости)
        $io->section('3. Testing cities fetch (first 3 cities only)');
        
        try {
            $io->text('Fetching cities...');
            
            $cities = $this->osmDataProvider->getCitiesInCountry(
                $latviaConfig->osmRelationId,
                $latviaConfig->adminLevelCity
            );
            
            $citiesCount = count($cities);
            
            if ($citiesCount === 0) {
                $io->warning('No cities found. This might indicate an issue with admin_level setting.');
                $io->note('Try using admin_level=9 or check OSM data for Latvia.');
            } else {
                $io->success(sprintf('Found %d cities', $citiesCount));
                
                // Показываем первые 3 города
                $io->text('First 3 cities:');
                $cityRows = [];
                
                foreach (array_slice($cities, 0, 3) as $index => $city) {
                    $cityRows[] = [
                        $index + 1,
                        $city['properties']['name'] ?? 'N/A',
                        $city['properties']['name:en'] ?? 'N/A',
                        $city['geometry']['type'] ?? 'N/A',
                    ];
                }
                
                $io->table(
                    ['#', 'Name', 'Name (EN)', 'Geometry Type'],
                    $cityRows
                );
            }
        } catch (\Exception $e) {
            $io->error([
                'Failed to fetch cities',
                'Error: ' . $e->getMessage(),
            ]);
            return Command::FAILURE;
        }

        // Итоги
        $io->section('Summary');
        $io->success([
            'All tests passed!',
            'OSM Overpass API is working correctly',
            'You can now run: php bin/console app:parse-geo-areas latvia',
        ]);

        return Command::SUCCESS;
    }

    /**
     * Проверить доступность Overpass API
     */
    private function checkApiAvailability(): bool
    {
        $ch = curl_init('https://overpass-api.de/api/status');
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200 && $response !== false;
    }
}

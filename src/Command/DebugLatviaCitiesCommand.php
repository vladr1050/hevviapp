<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-latvia-cities',
    description: 'Debug different queries for Latvia cities',
)]
class DebugLatviaCitiesCommand extends Command
{
    private const OVERPASS_API_URL = 'https://overpass-api.de/api/interpreter';
    private const RELATION_ID = '72594';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Debugging Latvia Cities Queries');

        $queries = [
            'Admin Level 9 (города)' => $this->buildQuery('relation["boundary"="administrative"]["admin_level"="9"]'),
            'Admin Level 10 (мелкие единицы)' => $this->buildQuery('relation["boundary"="administrative"]["admin_level"="10"]'),
            'Place=city relations' => $this->buildQuery('relation["place"="city"]'),
            'Place=town relations' => $this->buildQuery('relation["place"="town"]'),
            'Boundary + place=city' => $this->buildQuery('relation["boundary"="administrative"]["place"="city"]'),
        ];

        foreach ($queries as $name => $query) {
            $io->section($name);
            $io->text('Query: ' . $query);
            
            try {
                $result = $this->executeQuery($query);
                $count = count($result['elements'] ?? []);
                
                if ($count > 0) {
                    $io->success(sprintf('Found %d elements', $count));
                    
                    // Показываем первые 5
                    $io->text('First elements:');
                    foreach (array_slice($result['elements'], 0, 5) as $element) {
                        $name = $element['tags']['name'] ?? 'Unknown';
                        $type = $element['type'] ?? 'Unknown';
                        $place = $element['tags']['place'] ?? 'N/A';
                        $adminLevel = $element['tags']['admin_level'] ?? 'N/A';
                        
                        $io->writeln(sprintf(
                            '  - %s (type: %s, place: %s, admin_level: %s)',
                            $name,
                            $type,
                            $place,
                            $adminLevel
                        ));
                    }
                } else {
                    $io->warning('No elements found');
                }
                
            } catch (\Exception $e) {
                $io->error('Query failed: ' . $e->getMessage());
            }
            
            $io->newLine();
            sleep(3); // Задержка между запросами
        }

        return Command::SUCCESS;
    }

    private function buildQuery(string $selector): string
    {
        $areaId = 3600000000 + (int)self::RELATION_ID;
        return sprintf(
            '[out:json][timeout:60];area(%s)->.country;(%s(area.country););out tags;',
            $areaId,
            $selector
        );
    }

    private function executeQuery(string $query): array
    {
        $ch = curl_init();
        
        $postData = http_build_query(['data' => $query]);
        
        curl_setopt_array($ch, [
            CURLOPT_URL => self::OVERPASS_API_URL,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'Hevii BackOffice GeoArea Parser/1.0',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("HTTP {$httpCode}");
        }

        return json_decode($response, true) ?? [];
    }
}

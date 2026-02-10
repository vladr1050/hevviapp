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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда для отладки запросов к Overpass API
 */
#[AsCommand(
    name: 'app:debug-osm-query',
    description: 'Debug Overpass API query',
)]
class DebugOsmQueryCommand extends Command
{
    private const OVERPASS_API_URL = 'https://overpass-api.de/api/interpreter';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Debug Overpass API Query');

        // Тестовый запрос - самый простой для Латвии
        $relationId = '72594';
        
        // Попробуем несколько вариантов запросов
        $queries = [
            'Simple relation query' => sprintf('[out:json][timeout:25];relation(%s);out;', $relationId),
            'Relation with geom' => sprintf('[out:json][timeout:25];relation(%s);out geom;', $relationId),
            'Relation with body' => sprintf('[out:json][timeout:25];relation(%s);out body;', $relationId),
            'Relation with skeleton' => sprintf('[out:json][timeout:25];relation(%s);out skel;', $relationId),
        ];

        foreach ($queries as $name => $query) {
            $io->section($name);
            $io->text('Query: ' . $query);
            
            try {
                $result = $this->executeQuery($query);
                
                if (isset($result['elements']) && !empty($result['elements'])) {
                    $element = $result['elements'][0];
                    
                    $io->success('Query successful!');
                    $io->table(
                        ['Property', 'Value'],
                        [
                            ['Element type', $element['type'] ?? 'N/A'],
                            ['ID', $element['id'] ?? 'N/A'],
                            ['Has tags', isset($element['tags']) ? 'Yes (' . count($element['tags']) . ')' : 'No'],
                            ['Has members', isset($element['members']) ? 'Yes (' . count($element['members']) . ')' : 'No'],
                            ['Has geometry', isset($element['members'][0]['geometry']) ? 'Yes' : 'No'],
                        ]
                    );
                    
                    if (isset($element['tags']['name'])) {
                        $io->info('Name: ' . $element['tags']['name']);
                    }
                    
                    // Проверим первого member
                    if (isset($element['members'][0])) {
                        $member = $element['members'][0];
                        $io->text('First member: ' . json_encode([
                            'type' => $member['type'] ?? 'N/A',
                            'ref' => $member['ref'] ?? 'N/A',
                            'role' => $member['role'] ?? 'N/A',
                            'has_geometry' => isset($member['geometry']),
                            'geometry_points' => isset($member['geometry']) ? count($member['geometry']) : 0,
                        ], JSON_PRETTY_PRINT));
                    }
                    
                } else {
                    $io->warning('Query returned no elements');
                }
                
            } catch (\Exception $e) {
                $io->error('Query failed: ' . $e->getMessage());
            }
            
            $io->newLine();
            
            // Задержка между запросами
            if ($name !== array_key_last($queries)) {
                sleep(2);
            }
        }

        return Command::SUCCESS;
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
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Hevii BackOffice GeoArea Parser/1.0',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("cURL error: {$error}");
        }

        if ($httpCode !== 200) {
            $errorMessage = is_string($response) ? substr($response, 0, 500) : 'No response';
            throw new \RuntimeException("HTTP {$httpCode}: {$errorMessage}");
        }

        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON parse error: ' . json_last_error_msg());
        }

        return $data;
    }
}

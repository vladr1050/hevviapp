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

namespace App\Controller\Api;

use App\Entity\GeoArea;
use App\Repository\GeoAreaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GeoAreaController
 *
 * API контроллер для работы с гео-зонами.
 *
 * Следует принципам SOLID:
 * - Single Responsibility: отвечает только за API эндпоинты гео-зон
 * - Open/Closed: легко расширяемый новыми эндпоинтами
 * - Dependency Inversion: зависит от интерфейсов (Repository)
 */
#[Route('/geo-area', name: 'api_geo_area_')]
class GeoAreaController extends AbstractController
{
    public function __construct(
        private readonly GeoAreaRepository $geoAreaRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Получение списка стран (scope = 1)
     */
    #[Route('/countries', name: 'countries', methods: ['GET'])]
    public function getCountries(): JsonResponse
    {
        $countries = $this->geoAreaRepository->findBy(
            ['scope' => GeoArea::SCOPE['COUNTRY']],
            ['name' => 'ASC']
        );

        return $this->json(
            array_map(
                static fn(GeoArea $country) => [
                    'id' => (string) $country->getId(),
                    'name' => $country->getName(),
                    'countryISO3' => $country->getCountryISO3(),
                ],
                $countries
            )
        );
    }

    /**
     * Получение списка городов по ISO3 коду страны
     */
    #[Route('/cities', name: 'cities', methods: ['GET'])]
    public function getCitiesByCountry(Request $request): JsonResponse
    {
        $countryISO3 = $request->query->get('countryISO3');

        if (!$countryISO3) {
            return $this->json(['error' => 'countryISO3 parameter is required'], 400);
        }

        $cities = $this->geoAreaRepository->findBy(
            [
                'scope' => GeoArea::SCOPE['CITY'],
                'countryISO3' => $countryISO3,
            ],
            ['name' => 'ASC']
        );

        return $this->json(
            array_map(
                static fn(GeoArea $city) => [
                    'id' => (string) $city->getId(),
                    'name' => $city->getName(),
                    'countryISO3' => $city->getCountryISO3(),
                ],
                $cities
            )
        );
    }

    /**
     * Получение геометрии гео-зоны по ID
     */
    #[Route('/{id}/geometry', name: 'geometry', methods: ['GET'])]
    public function getGeometry(string $id): JsonResponse
    {
        $geoArea = $this->geoAreaRepository->find($id);

        if (!$geoArea) {
            return $this->json(['error' => 'GeoArea not found', 'id' => $id], 404);
        }

        // Используем PostGIS функцию ST_AsGeoJSON для конвертации геометрии
        // UUID нужно конвертировать в строку для SQL запроса
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT ST_AsGeoJSON(geometry) as geojson FROM geo_area WHERE id::text = :id';
        $result = $conn->executeQuery($sql, ['id' => $id]);
        $row = $result->fetchAssociative();

        if (!$row || !$row['geojson']) {
            return $this->json([
                'error' => 'Geometry not found',
                'id' => $id,
                'debug' => 'Check if geometry column has data'
            ], 404);
        }

        $geometry = json_decode($row['geojson'], true);

        return $this->json([
            'id' => (string) $geoArea->getId(),
            'name' => $geoArea->getName(),
            'countryISO3' => $geoArea->getCountryISO3(),
            'scope' => $geoArea->getScope(),
            'geometry' => $geometry,
        ]);
    }

    /**
     * Получение списка кастомных зон по ISO3 коду страны
     */
    #[Route('/custom-areas', name: 'custom_areas', methods: ['GET'])]
    public function getCustomAreasByCountry(Request $request): JsonResponse
    {
        $countryISO3 = $request->query->get('countryISO3');

        if (!$countryISO3) {
            return $this->json(['error' => 'countryISO3 parameter is required'], 400);
        }

        $customAreas = $this->geoAreaRepository->findCustomAreasByCountryISO3($countryISO3);

        return $this->json(
            array_map(
                static fn(GeoArea $area) => [
                    'id' => (string) $area->getId(),
                    'name' => $area->getName(),
                    'countryISO3' => $area->getCountryISO3(),
                ],
                $customAreas
            )
        );
    }

    /**
     * Создание/сохранение кастомной зоны напрямую в БД
     */
    #[Route('/custom-area', name: 'custom_area_create', methods: ['POST'])]
    public function createCustomArea(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['name'], $data['geometry'], $data['countryISO3'])) {
            return $this->json([
                'error' => 'Missing required fields: name, geometry, countryISO3'
            ], 400);
        }

        // Валидация геометрии (должна быть GeoJSON)
        if (!isset($data['geometry']['type'], $data['geometry']['coordinates'])) {
            return $this->json([
                'error' => 'Invalid geometry format. Expected GeoJSON with type and coordinates'
            ], 400);
        }

        try {
            // Создаем новую GeoArea
            $geoArea = new GeoArea();
            $geoArea->setName($data['name']);
            $geoArea->setCountryISO3($data['countryISO3']);
            $geoArea->setScope(GeoArea::SCOPE['CUSTOM_AREA']);

            // Конвертируем GeoJSON в WKT для PostGIS
            $geometry = $this->convertGeoJsonToWkt($data['geometry']);
            $geoArea->setGeometry($geometry);

            // Сохраняем в БД
            $this->entityManager->persist($geoArea);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'id' => (string) $geoArea->getId(),
                'name' => $geoArea->getName(),
                'message' => 'Custom area created successfully',
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to create custom area: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновление кастомной зоны
     */
    #[Route('/custom-area/{id}', name: 'custom_area_update', methods: ['PUT'])]
    public function updateCustomArea(string $id, Request $request): JsonResponse
    {
        $geoArea = $this->geoAreaRepository->find($id);

        if (!$geoArea) {
            error_log("GeoArea not found: {$id}");
            return $this->json(['error' => 'GeoArea not found'], 404);
        }

        // Проверяем что это кастомная зона
        if ($geoArea->getScope() !== GeoArea::SCOPE['CUSTOM_AREA']) {
            error_log("Not a custom area: scope = {$geoArea->getScope()}");
            return $this->json(['error' => 'Only custom areas can be updated via this endpoint'], 400);
        }

        $data = json_decode($request->getContent(), true);

        error_log("Update custom area request: " . json_encode([
            'id' => $id,
            'name' => $data['name'] ?? 'missing',
            'geometry_type' => $data['geometry']['type'] ?? 'missing',
            'has_coordinates' => isset($data['geometry']['coordinates']),
        ]));

        if (!$data || !isset($data['name'], $data['geometry'])) {
            return $this->json([
                'error' => 'Missing required fields: name, geometry'
            ], 400);
        }

        try {
            $oldName = $geoArea->getName();

            // Обновляем данные
            $geoArea->setName($data['name']);

            // Конвертируем GeoJSON в WKT для PostGIS
            $geometry = $this->convertGeoJsonToWkt($data['geometry']);
            $geoArea->setGeometry($geometry);

            $this->entityManager->flush();

            error_log("Custom area updated successfully: {$oldName} -> {$data['name']}");

            return $this->json([
                'success' => true,
                'id' => (string) $geoArea->getId(),
                'name' => $geoArea->getName(),
                'message' => 'Custom area updated successfully',
            ]);

        } catch (\Exception $e) {
            error_log("Failed to update custom area: " . $e->getMessage());
            return $this->json([
                'error' => 'Failed to update custom area: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Конвертировать GeoJSON в WKT (Well-Known Text) для PostGIS
     *
     * @param array $geoJson
     * @return string WKT представление геометрии
     */
    private function convertGeoJsonToWkt(array $geoJson): string
    {
        $type = strtoupper($geoJson['type']);
        $coordinates = $geoJson['coordinates'];

        // Для MultiPolygon
        if ($type === 'MULTIPOLYGON') {
            $polygons = [];
            foreach ($coordinates as $polygon) {
                $rings = [];
                foreach ($polygon as $ring) {
                    $points = array_map(
                        fn($coord) => $coord[0] . ' ' . $coord[1],
                        $ring
                    );
                    $rings[] = '(' . implode(', ', $points) . ')';
                }
                $polygons[] = '(' . implode(', ', $rings) . ')';
            }
            return "MULTIPOLYGON(" . implode(', ', $polygons) . ")";
        }

        // Для Polygon
        if ($type === 'POLYGON') {
            $rings = [];
            foreach ($coordinates as $ring) {
                $points = array_map(
                    fn($coord) => $coord[0] . ' ' . $coord[1],
                    $ring
                );
                $rings[] = '(' . implode(', ', $points) . ')';
            }
            return "POLYGON(" . implode(', ', $rings) . ")";
        }

        throw new \InvalidArgumentException("Unsupported geometry type: {$type}");
    }

    /**
     * Удаление кастомной зоны
     */
    #[Route('/custom-area/{id}', name: 'custom_area_delete', methods: ['DELETE'])]
    public function deleteCustomArea(string $id): JsonResponse
    {
        $geoArea = $this->geoAreaRepository->find($id);

        if (!$geoArea) {
            return $this->json(['error' => 'GeoArea not found'], 404);
        }

        // Проверяем что это кастомная зона
        if ($geoArea->getScope() !== GeoArea::SCOPE['CUSTOM_AREA']) {
            return $this->json(['error' => 'Only custom areas can be deleted via this endpoint'], 400);
        }

        try {
            $this->entityManager->remove($geoArea);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Custom area deleted successfully',
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to delete custom area: ' . $e->getMessage()
            ], 500);
        }
    }
}

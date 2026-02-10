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
                fn(GeoArea $country) => [
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
                fn(GeoArea $city) => [
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
            'geometry' => $geometry,
        ]);
    }
}

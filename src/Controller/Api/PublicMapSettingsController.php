<?php

/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Controller\Api;

use App\Repository\AppSettingsRepository;
use App\Service\Map\GoogleGeocodeProxyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Read-only JSON for the public SPA: map defaults and geocoding restriction hints (no secrets).
 */
final class PublicMapSettingsController extends AbstractController
{
    public function __construct(
        private readonly AppSettingsRepository $appSettingsRepository,
        private readonly ParameterBagInterface $parameterBag,
        private readonly GoogleGeocodeProxyService $googleGeocodeProxy,
    ) {
    }

    #[Route('/public/map-settings', name: 'api_public_map_settings', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $s = $this->appSettingsRepository->getSingleton();

        $defaultLat = $s?->getDefaultMapLatitude() ?? (float) $this->parameterBag->get('app.map.default_latitude');
        $defaultLng = $s?->getDefaultMapLongitude() ?? (float) $this->parameterBag->get('app.map.default_longitude');
        $defaultZoom = $s?->getDefaultMapZoom() ?? (int) $this->parameterBag->get('app.map.default_zoom');

        $restrict = $s?->isRestrictGeographicSearch() ?? false;
        $countryCodes = $s?->getNominatimCountryCodes();
        $trimmedCodes = $countryCodes !== null && trim($countryCodes) !== '' ? trim($countryCodes) : null;

        $bbox = null;
        if ($s !== null && $s->hasCompleteBoundingBox()) {
            $bbox = [
                'minLatitude'  => $s->getBboxMinLatitude(),
                'maxLatitude'  => $s->getBboxMaxLatitude(),
                'minLongitude' => $s->getBboxMinLongitude(),
                'maxLongitude' => $s->getBboxMaxLongitude(),
            ];
        }

        $maxBounds = null;
        if ($bbox !== null) {
            $maxBounds = [
                [$bbox['minLatitude'], $bbox['minLongitude']],
                [$bbox['maxLatitude'], $bbox['maxLongitude']],
            ];
        }

        return $this->json([
            'restrictGeographicSearch' => $restrict,
            'nominatimCountryCodes'    => $trimmedCodes,
            'boundingBox'              => $bbox,
            'map'                      => [
                'center'    => ['latitude' => $defaultLat, 'longitude' => $defaultLng],
                'zoom'      => $defaultZoom,
                'maxBounds' => $maxBounds,
            ],
            'nominatimApiUrl'      => $this->parameterBag->get('app.map.nominatim_api_url'),
            'googleAddressSearch' => $this->googleGeocodeProxy->isConfigured(),
        ]);
    }
}

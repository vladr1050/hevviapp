<?php

/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Controller\Api;

use App\Entity\AppSettings;
use App\Exception\Map\GoogleGeocodeProxyException;
use App\Repository\AppSettingsRepository;
use App\Service\Map\GoogleGeocodeProxyService;
use App\Service\Map\NominatimGeocodeService;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Places API (New) Autocomplete + Geocoding API behind the Symfony app (API key stays on the server).
 * Falls back to Nominatim when Google returns a transient/config error.
 */
final class PublicGeocodeController extends AbstractController
{
    public function __construct(
        private readonly GoogleGeocodeProxyService $googleGeocodeProxy,
        private readonly NominatimGeocodeService $nominatimGeocode,
        private readonly AppSettingsRepository $appSettingsRepository,
        private readonly ParameterBagInterface $parameterBag,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/public/geocode/autocomplete', name: 'api_public_geocode_autocomplete', methods: ['POST'])]
    public function autocomplete(Request $request): JsonResponse
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->json(['error' => 'Invalid JSON body.'], 400);
        }

        $input = $payload['input'] ?? null;
        if (!\is_string($input)) {
            return $this->json(['error' => 'Field "input" must be a string.'], 400);
        }
        $input = trim($input);
        if ($input === '' || \strlen($input) < 2) {
            return $this->json(['predictions' => []]);
        }
        if (\strlen($input) > 256) {
            return $this->json(['error' => 'Field "input" is too long.'], 400);
        }

        $sessionToken = $this->normalizeSessionToken($payload['sessionToken'] ?? null);
        if ($sessionToken === false) {
            return $this->json(['error' => 'Invalid session token.'], 400);
        }

        $settings = $this->appSettingsRepository->getSingleton();

        if ($this->googleGeocodeProxy->isConfigured()) {
            try {
                $predictions = $this->googleGeocodeProxy->autocomplete(
                    $input,
                    $sessionToken,
                    $this->buildPlacesAutocompleteFragment($settings),
                );

                return $this->json(['predictions' => $predictions]);
            } catch (GoogleGeocodeProxyException $e) {
                if ($e->getHttpStatus() === 400) {
                    return $this->json(['error' => $e->getMessage()], 400);
                }
                $this->logger->warning('Google autocomplete failed; falling back to Nominatim.', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        try {
            $predictions = $this->nominatimGeocode->autocomplete(
                $input,
                $this->nominatimCountryCodes($settings),
                $this->nominatimViewbox($settings),
            );
        } catch (GoogleGeocodeProxyException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getHttpStatus());
        }

        return $this->json(['predictions' => $predictions]);
    }

    #[Route('/public/geocode/place', name: 'api_public_geocode_place', methods: ['POST'])]
    public function place(Request $request): JsonResponse
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->json(['error' => 'Invalid JSON body.'], 400);
        }

        $placeId = $payload['placeId'] ?? null;
        if (!\is_string($placeId) || $placeId === '') {
            return $this->json(['error' => 'Field "placeId" is required.'], 400);
        }
        if (\strlen($placeId) > 512) {
            return $this->json(['error' => 'Field "placeId" is too long.'], 400);
        }

        $sessionToken = $this->normalizeSessionToken($payload['sessionToken'] ?? null);
        if ($sessionToken === false) {
            return $this->json(['error' => 'Invalid session token.'], 400);
        }

        if (NominatimGeocodeService::isNominatimPlaceId($placeId)) {
            try {
                $dto = $this->nominatimGeocode->resolvePlaceId($placeId);
            } catch (GoogleGeocodeProxyException $e) {
                return $this->json(['error' => $e->getMessage()], $e->getHttpStatus());
            }

            return $this->json($dto->toApiArray());
        }

        if (!$this->googleGeocodeProxy->isConfigured()) {
            return $this->json(['error' => 'Address search is not available.'], 503);
        }

        try {
            $dto = $this->googleGeocodeProxy->geocodeByPlaceId($placeId, $sessionToken);
        } catch (GoogleGeocodeProxyException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getHttpStatus());
        }

        return $this->json($dto->toApiArray());
    }

    #[Route('/public/geocode/reverse', name: 'api_public_geocode_reverse', methods: ['GET'])]
    public function reverse(Request $request): JsonResponse
    {
        $lat = $this->readCoordinate($request->query->get('lat'));
        $lng = $this->readCoordinate($request->query->get('lng'));
        if ($lat === null || $lng === null) {
            return $this->json(['error' => 'Query parameters "lat" and "lng" must be valid numbers.'], 400);
        }
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return $this->json(['error' => 'Coordinates are out of range.'], 400);
        }

        if ($this->googleGeocodeProxy->isConfigured()) {
            try {
                $dto = $this->googleGeocodeProxy->reverse($lat, $lng);

                return $this->json($dto->toApiArray());
            } catch (GoogleGeocodeProxyException $e) {
                if ($e->getHttpStatus() === 404) {
                    return $this->json(['error' => $e->getMessage()], 404);
                }
                $this->logger->warning('Google reverse geocode failed; falling back to Nominatim.', [
                    'message' => $e->getMessage(),
                    'lat'     => $lat,
                    'lng'     => $lng,
                ]);
            }
        }

        try {
            $dto = $this->nominatimGeocode->reverse($lat, $lng);
        } catch (GoogleGeocodeProxyException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getHttpStatus());
        }

        return $this->json($dto->toApiArray());
    }

    /**
     * JSON fragment for Places API (New) Autocomplete (merged with `input` / `sessionToken` in the service).
     * `locationBias` and `locationRestriction` must not both be set on one request.
     *
     * @return array<string, mixed>
     */
    private function buildPlacesAutocompleteFragment(?AppSettings $s): array
    {
        $defaultLat = $s?->getDefaultMapLatitude() ?? (float) $this->parameterBag->get('app.map.default_latitude');
        $defaultLng = $s?->getDefaultMapLongitude() ?? (float) $this->parameterBag->get('app.map.default_longitude');

        $restrict = $s?->isRestrictGeographicSearch() ?? false;
        $codes = $restrict ? $this->parseCountryCodes($s?->getNominatimCountryCodes()) : [];

        // Do not set includedPrimaryTypes here: legacy "address" behaviour is hard to mirror with
        // five primary types only; a narrow list often yields zero predictions for partial street input.

        $fragment = [
            'regionCode' => $codes[0] ?? 'lv',
        ];

        if ($codes !== []) {
            $fragment['includedRegionCodes'] = $codes;
        }

        // Prefer Latvian UI strings when search is LV-only (or unrestricted default region LV).
        $fragment['languageCode'] = ($codes === [] || $codes === ['lv']) ? 'lv' : 'en';

        if ($restrict && $s !== null && $s->hasCompleteBoundingBox()) {
            $minLat = $s->getBboxMinLatitude();
            $maxLat = $s->getBboxMaxLatitude();
            $minLng = $s->getBboxMinLongitude();
            $maxLng = $s->getBboxMaxLongitude();
            if ($minLat !== null && $maxLat !== null && $minLng !== null && $maxLng !== null) {
                $fragment['locationRestriction'] = [
                    'rectangle' => [
                        'low' => [
                            'latitude'  => $minLat,
                            'longitude' => $minLng,
                        ],
                        'high' => [
                            'latitude'  => $maxLat,
                            'longitude' => $maxLng,
                        ],
                    ],
                ];

                return $fragment;
            }
        }

        // API caps circle radius at 50 km; bias only (results may still appear outside).
        $fragment['locationBias'] = [
            'circle' => [
                'center' => [
                    'latitude'  => $defaultLat,
                    'longitude' => $defaultLng,
                ],
                'radius' => 50000.0,
            ],
        ];

        return $fragment;
    }

    /**
     * @return list<string>
     */
    private function nominatimCountryCodes(?AppSettings $s): array
    {
        if ($s === null || !$s->isRestrictGeographicSearch()) {
            return [];
        }

        return $this->parseCountryCodes($s->getNominatimCountryCodes());
    }

    /**
     * @return array{minLatitude: float, maxLatitude: float, minLongitude: float, maxLongitude: float}|null
     */
    private function nominatimViewbox(?AppSettings $s): ?array
    {
        if ($s === null || !$s->isRestrictGeographicSearch() || !$s->hasCompleteBoundingBox()) {
            return null;
        }
        $minLat = $s->getBboxMinLatitude();
        $maxLat = $s->getBboxMaxLatitude();
        $minLng = $s->getBboxMinLongitude();
        $maxLng = $s->getBboxMaxLongitude();
        if ($minLat === null || $maxLat === null || $minLng === null || $maxLng === null) {
            return null;
        }

        return [
            'minLatitude'  => $minLat,
            'maxLatitude'  => $maxLat,
            'minLongitude' => $minLng,
            'maxLongitude' => $maxLng,
        ];
    }

    /**
     * @return list<string>
     */
    private function parseCountryCodes(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $parts = preg_split('/[,\s;]+/', strtolower(trim($raw)));
        if ($parts === false) {
            return [];
        }
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '' && preg_match('/^[a-z]{2}$/', $p) === 1) {
                $out[] = $p;
            }
        }

        return array_values(array_unique($out));
    }

    private function readCoordinate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (\is_float($value) || \is_int($value)) {
            $f = (float) $value;

            return is_finite($f) ? $f : null;
        }
        if (!\is_string($value)) {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        $f = (float) $value;

        return is_finite($f) ? $f : null;
    }

    private function normalizeSessionToken(mixed $token): string|false|null
    {
        if ($token === null) {
            return null;
        }
        if ($token === '') {
            return null;
        }
        if (!\is_string($token)) {
            return false;
        }
        if (\strlen($token) > 200) {
            return false;
        }
        if (preg_match('/^[\x20-\x7E]+$/', $token) !== 1) {
            return false;
        }

        return $token;
    }
}

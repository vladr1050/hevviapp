<?php

/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Service;

use App\Entity\AppSettings;
use App\Repository\AppSettingsRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ensures pickup/dropoff coordinates respect Sonata-configured geography when restriction is enabled.
 */
final class GeographicOrderCoordinatesValidator
{
    public function __construct(
        private readonly AppSettingsRepository $appSettingsRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly string $nominatimUrl,
        private readonly string $nominatimUserAgent,
    ) {
    }

    /**
     * @return non-empty-string|null Translation key in AppBundle when invalid; null when OK or nothing to validate
     */
    public function validateOrderCoordinates(
        ?string $pickupLatitude,
        ?string $pickupLongitude,
        ?string $dropoutLatitude,
        ?string $dropoutLongitude,
    ): ?string {
        $settings = $this->appSettingsRepository->getSingleton();
        if ($settings === null || !$settings->isRestrictGeographicSearch()) {
            return null;
        }

        $hasBbox = $settings->hasCompleteBoundingBox();
        $countryCodes = $this->normalizeCountryCodes($settings->getNominatimCountryCodes());
        if (!$hasBbox && $countryCodes === []) {
            return null;
        }

        $pairs = [
            ['lat' => $pickupLatitude, 'lng' => $pickupLongitude],
            ['lat' => $dropoutLatitude, 'lng' => $dropoutLongitude],
        ];

        foreach ($pairs as $pair) {
            $lat = $this->parseCoordinate($pair['lat']);
            $lng = $this->parseCoordinate($pair['lng']);
            if ($lat === null || $lng === null) {
                continue;
            }

            if ($hasBbox) {
                $err = $this->validateBbox($lat, $lng, $settings);
                if ($err !== null) {
                    return $err;
                }
            }

            if ($countryCodes !== []) {
                $err = $this->validateCountry($lat, $lng, $countryCodes);
                if ($err !== null) {
                    return $err;
                }
            }
        }

        return null;
    }

    private function validateBbox(float $lat, float $lng, AppSettings $settings): ?string
    {
        $minLat = $settings->getBboxMinLatitude();
        $maxLat = $settings->getBboxMaxLatitude();
        $minLon = $settings->getBboxMinLongitude();
        $maxLon = $settings->getBboxMaxLongitude();
        if ($minLat === null || $maxLat === null || $minLon === null || $maxLon === null) {
            return null;
        }

        if ($lat < $minLat || $lat > $maxLat || $lng < $minLon || $lng > $maxLon) {
            return 'api.order.coordinates_outside_bounding_box';
        }

        return null;
    }

    /**
     * @param list<non-empty-string> $allowedLower
     */
    private function validateCountry(float $lat, float $lng, array $allowedLower): ?string
    {
        $url = rtrim($this->nominatimUrl, '/').'/reverse';
        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => [
                    'lat'   => $lat,
                    'lon'   => $lng,
                    'format' => 'json',
                    'addressdetails' => '1',
                ],
                'headers' => [
                    'User-Agent' => $this->nominatimUserAgent,
                ],
                'timeout' => 6,
            ]);
            if ($response->getStatusCode() !== 200) {
                return 'api.order.coordinates_country_check_failed';
            }
            $data = $response->toArray(false);
        } catch (\Throwable) {
            return 'api.order.coordinates_country_check_failed';
        }

        $code = isset($data['address']['country_code']) ? strtolower((string) $data['address']['country_code']) : '';
        if ($code === '' || !in_array($code, $allowedLower, true)) {
            return 'api.order.coordinates_outside_allowed_countries';
        }

        return null;
    }

    /**
     * @return list<non-empty-string>
     */
    private function normalizeCountryCodes(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[,\s;]+/', strtolower($raw), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) {
            return [];
        }

        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return $out;
    }

    private function parseCoordinate(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}

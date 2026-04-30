<?php

/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Service\Map;

use App\Dto\Map\GeocodeResolvedAddressDto;
use App\Exception\Map\GoogleGeocodeProxyException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Server-side calls to Places API (New) Autocomplete, plus Geocoding API for coordinates (key never exposed to the browser).
 */
final class GoogleGeocodeProxyService
{
    private const PLACES_AUTOCOMPLETE_URL = 'https://places.googleapis.com/v1/places:autocomplete';

    /** Minimal response fields (billing); spaces are not allowed in the mask list. */
    private const AUTOCOMPLETE_FIELD_MASK = 'suggestions.placePrediction.placeId,suggestions.placePrediction.text.text';

    private const GEOCODE_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->apiKey) !== '';
    }

    /**
     * @param array<string, mixed> $bodyFragment JSON fields merged into Autocomplete (New) (e.g. locationBias, includedRegionCodes).
     *
     * @return list<array{description: string, placeId: string}>
     */
    public function autocomplete(string $input, ?string $sessionToken, array $bodyFragment = []): array
    {
        $body = array_merge([
            'input'         => $input,
            'languageCode'  => 'en',
        ], $bodyFragment);

        if ($sessionToken !== null && $sessionToken !== '') {
            $body['sessionToken'] = $sessionToken;
        }

        $response = $this->httpClient->request('POST', self::PLACES_AUTOCOMPLETE_URL, [
            'headers' => [
                'Content-Type'       => 'application/json',
                'X-Goog-Api-Key'     => $this->apiKey,
                'X-Goog-FieldMask'   => self::AUTOCOMPLETE_FIELD_MASK,
            ],
            'json' => $body,
        ]);

        $statusCode = $response->getStatusCode();
        $raw = $response->getContent(false);

        if ($statusCode === 400) {
            throw new GoogleGeocodeProxyException($this->parsePlacesErrorMessage($raw) ?? 'Invalid geocoding request.', 400);
        }
        if ($statusCode !== 200) {
            throw new GoogleGeocodeProxyException('Geocoding service temporarily unavailable.', 502);
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new GoogleGeocodeProxyException('Geocoding service returned an error.', 502);
        }
        if (!\is_array($data)) {
            throw new GoogleGeocodeProxyException('Geocoding service returned an error.', 502);
        }

        $suggestions = $data['suggestions'] ?? [];
        if (!\is_array($suggestions)) {
            return [];
        }

        $out = [];
        foreach ($suggestions as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $pp = $row['placePrediction'] ?? null;
            if (!\is_array($pp)) {
                continue;
            }
            $placeId = isset($pp['placeId']) && \is_string($pp['placeId']) ? $pp['placeId'] : '';
            $textNode = $pp['text'] ?? null;
            $desc = '';
            if (\is_array($textNode) && isset($textNode['text']) && \is_string($textNode['text'])) {
                $desc = $textNode['text'];
            }
            if ($desc === '' || $placeId === '') {
                continue;
            }
            $out[] = ['description' => $desc, 'placeId' => $placeId];
        }

        return $out;
    }

    private function parsePlacesErrorMessage(string $raw): ?string
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!\is_array($decoded)) {
            return null;
        }
        $err = $decoded['error'] ?? null;
        if (!\is_array($err)) {
            return null;
        }
        $msg = $err['message'] ?? null;

        return \is_string($msg) && $msg !== '' ? $msg : null;
    }

    public function geocodeByPlaceId(string $placeId, ?string $sessionToken): GeocodeResolvedAddressDto
    {
        $query = [
            'place_id' => $placeId,
            'key'      => $this->apiKey,
        ];
        if ($sessionToken !== null && $sessionToken !== '') {
            $query['sessiontoken'] = $sessionToken;
        }

        return $this->requestGeocode($query);
    }

    public function reverse(float $lat, float $lng): GeocodeResolvedAddressDto
    {
        $query = [
            'latlng' => sprintf('%F,%F', $lat, $lng),
            'key'    => $this->apiKey,
        ];

        return $this->requestGeocode($query);
    }

    /**
     * @param array<string, string> $query
     */
    private function requestGeocode(array $query): GeocodeResolvedAddressDto
    {
        $response = $this->httpClient->request('GET', self::GEOCODE_URL, ['query' => $query]);
        if ($response->getStatusCode() !== 200) {
            throw new GoogleGeocodeProxyException('Geocoding service temporarily unavailable.', 502);
        }

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return $this->parseGeocodePayload($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseGeocodePayload(array $data): GeocodeResolvedAddressDto
    {
        $status = isset($data['status']) && \is_string($data['status']) ? $data['status'] : '';
        if ($status === 'ZERO_RESULTS') {
            throw new GoogleGeocodeProxyException('No results for this location.', 404);
        }
        if ($status !== 'OK') {
            throw new GoogleGeocodeProxyException('Geocoding service returned an error.', 502);
        }

        $results = $data['results'] ?? null;
        if (!\is_array($results) || $results === [] || !\is_array($results[0])) {
            throw new GoogleGeocodeProxyException('No results for this location.', 404);
        }

        $r = $results[0];
        $formatted = isset($r['formatted_address']) && \is_string($r['formatted_address'])
            ? $r['formatted_address']
            : '';
        if ($formatted === '') {
            throw new GoogleGeocodeProxyException('No results for this location.', 404);
        }

        $geometry = $r['geometry'] ?? null;
        if (!\is_array($geometry)) {
            throw new GoogleGeocodeProxyException('Geocoding service returned an error.', 502);
        }
        $loc = $geometry['location'] ?? null;
        if (!\is_array($loc)) {
            throw new GoogleGeocodeProxyException('Geocoding service returned an error.', 502);
        }
        $lat = $loc['lat'] ?? null;
        $lng = $loc['lng'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng)) {
            throw new GoogleGeocodeProxyException('Geocoding service returned an error.', 502);
        }

        $countryCode = null;
        $components = $r['address_components'] ?? null;
        if (\is_array($components)) {
            foreach ($components as $c) {
                if (!\is_array($c)) {
                    continue;
                }
                $types = $c['types'] ?? null;
                if (!\is_array($types) || !\in_array('country', $types, true)) {
                    continue;
                }
                $short = $c['short_name'] ?? null;
                if (\is_string($short) && $short !== '') {
                    $countryCode = strtolower($short);
                }
                break;
            }
        }

        return new GeocodeResolvedAddressDto(
            $formatted,
            (float) $lat,
            (float) $lng,
            $countryCode,
        );
    }

}

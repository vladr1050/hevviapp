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
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Server-side OSM Nominatim search / reverse / lookup (fallback when Google geocoding is down).
 */
final class NominatimGeocodeService
{
    private const PLACE_ID_PREFIX = 'nominatim:';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $nominatimUrl,
        private readonly string $nominatimUserAgent,
    ) {
    }

    public static function isNominatimPlaceId(string $placeId): bool
    {
        return str_starts_with($placeId, self::PLACE_ID_PREFIX);
    }

    /**
     * @param list<string> $countryCodes lowercase ISO-3166-1 alpha-2
     *
     * @return list<array{description: string, placeId: string}>
     */
    public function autocomplete(string $input, array $countryCodes = [], ?array $viewbox = null): array
    {
        $query = [
            'q'              => $input,
            'format'         => 'json',
            'addressdetails' => '1',
            'limit'          => '8',
            'accept-language'=> 'en',
        ];
        if ($countryCodes !== []) {
            $query['countrycodes'] = implode(',', $countryCodes);
        }
        if ($viewbox !== null) {
            // west,north,east,south — same order as the SPA Nominatim path
            $query['viewbox'] = sprintf(
                '%F,%F,%F,%F',
                $viewbox['minLongitude'],
                $viewbox['maxLatitude'],
                $viewbox['maxLongitude'],
                $viewbox['minLatitude'],
            );
            $query['bounded'] = '1';
        }

        $rows = $this->getJson('/search', $query);
        if (!\is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $display = isset($row['display_name']) && \is_string($row['display_name']) ? $row['display_name'] : '';
            $osmType = isset($row['osm_type']) && \is_string($row['osm_type']) ? $row['osm_type'] : '';
            $osmId = $row['osm_id'] ?? null;
            if ($display === '' || $osmType === '' || !is_numeric($osmId)) {
                continue;
            }
            $prefix = match (strtolower($osmType)) {
                'node' => 'N',
                'way' => 'W',
                'relation' => 'R',
                default => null,
            };
            if ($prefix === null) {
                continue;
            }
            $out[] = [
                'description' => $display,
                'placeId'     => self::PLACE_ID_PREFIX.$prefix.(int) $osmId,
            ];
        }

        return $out;
    }

    public function resolvePlaceId(string $placeId): GeocodeResolvedAddressDto
    {
        if (!self::isNominatimPlaceId($placeId)) {
            throw new GoogleGeocodeProxyException('Invalid place id.', 400);
        }
        $osmIds = substr($placeId, \strlen(self::PLACE_ID_PREFIX));
        if ($osmIds === '' || preg_match('/^[NWR]\d+$/', $osmIds) !== 1) {
            throw new GoogleGeocodeProxyException('Invalid place id.', 400);
        }

        $rows = $this->getJson('/lookup', [
            'osm_ids'        => $osmIds,
            'format'         => 'json',
            'addressdetails' => '1',
        ]);
        if (!\is_array($rows) || $rows === [] || !\is_array($rows[0])) {
            throw new GoogleGeocodeProxyException('No results for this location.', 404);
        }

        return $this->dtoFromNominatimRow($rows[0]);
    }

    public function reverse(float $lat, float $lng): GeocodeResolvedAddressDto
    {
        $row = $this->getJson('/reverse', [
            'lat'            => sprintf('%F', $lat),
            'lon'            => sprintf('%F', $lng),
            'format'         => 'json',
            'addressdetails' => '1',
        ]);
        if (!\is_array($row) || isset($row['error'])) {
            throw new GoogleGeocodeProxyException('No results for this location.', 404);
        }

        return $this->dtoFromNominatimRow($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function dtoFromNominatimRow(array $row): GeocodeResolvedAddressDto
    {
        $display = isset($row['display_name']) && \is_string($row['display_name']) ? $row['display_name'] : '';
        $lat = $row['lat'] ?? null;
        $lng = $row['lon'] ?? null;
        if ($display === '' || !is_numeric($lat) || !is_numeric($lng)) {
            throw new GoogleGeocodeProxyException('Geocoding service returned an error.', 502);
        }

        $countryCode = null;
        $address = $row['address'] ?? null;
        if (\is_array($address)) {
            $cc = $address['country_code'] ?? null;
            if (\is_string($cc) && $cc !== '') {
                $countryCode = strtolower($cc);
            }
        }

        return new GeocodeResolvedAddressDto(
            $display,
            (float) $lat,
            (float) $lng,
            $countryCode,
        );
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<mixed>|null
     */
    private function getJson(string $path, array $query): ?array
    {
        $url = rtrim($this->nominatimUrl, '/').$path;
        try {
            $response = $this->httpClient->request('GET', $url, [
                'query'   => $query,
                'headers' => [
                    'User-Agent' => $this->nominatimUserAgent,
                    'Accept'     => 'application/json',
                ],
                'timeout' => 8,
            ]);
            if ($response->getStatusCode() !== 200) {
                throw new GoogleGeocodeProxyException('Geocoding service temporarily unavailable.', 502);
            }
            /** @var mixed $data */
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface) {
            throw new GoogleGeocodeProxyException('Geocoding service temporarily unavailable.', 502);
        } catch (GoogleGeocodeProxyException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new GoogleGeocodeProxyException('Geocoding service temporarily unavailable.', 502);
        }

        return \is_array($data) ? $data : null;
    }
}

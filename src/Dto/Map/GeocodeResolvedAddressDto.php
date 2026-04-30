<?php

/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Dto\Map;

/**
 * Unified address resolution payload for the SPA (autocomplete follow-up, reverse geocode).
 */
final readonly class GeocodeResolvedAddressDto
{
    public function __construct(
        public string $displayLine,
        public float $latitude,
        public float $longitude,
        public ?string $countryCode,
    ) {
    }

    /**
     * @return array{displayLine: string, latitude: float, longitude: float, countryCode: string|null}
     */
    public function toApiArray(): array
    {
        return [
            'displayLine'   => $this->displayLine,
            'latitude'      => $this->latitude,
            'longitude'     => $this->longitude,
            'countryCode'   => $this->countryCode,
        ];
    }
}

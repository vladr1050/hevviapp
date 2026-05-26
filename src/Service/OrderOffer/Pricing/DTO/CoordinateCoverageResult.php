<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Pricing\DTO;

use App\Entity\GeoArea;
use App\Entity\ServiceArea;

/**
 * GeoArea containing a point and the carrier's ServiceArea used for matrix pricing.
 */
final readonly class CoordinateCoverageResult
{
    public function __construct(
        public GeoArea $geoArea,
        public ServiceArea $serviceArea,
    ) {
    }
}

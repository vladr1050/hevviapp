<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Pricing;

use App\Enum\PricingAlgorithm;

final class PricingStrategyRegistry
{
    public function __construct(
        private readonly FlatByDropOffZonePricingStrategy $flatByDropOffZone,
        private readonly HubAndSpokePricingStrategy $hubAndSpoke,
    ) {
    }

    public function get(PricingAlgorithm $algorithm): PricingStrategyInterface
    {
        return match ($algorithm) {
            PricingAlgorithm::FLAT_BY_DROP_OFF_ZONE => $this->flatByDropOffZone,
            PricingAlgorithm::HUB_AND_SPOKE => $this->hubAndSpoke,
        };
    }
}

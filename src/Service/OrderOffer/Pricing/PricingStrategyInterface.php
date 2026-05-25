<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Pricing;

use App\Service\OrderOffer\Pricing\DTO\FreightResolutionResult;
use App\Service\OrderOffer\Pricing\DTO\PricingCalculationContext;

interface PricingStrategyInterface
{
    public function resolveBaseFreight(PricingCalculationContext $context): FreightResolutionResult;
}

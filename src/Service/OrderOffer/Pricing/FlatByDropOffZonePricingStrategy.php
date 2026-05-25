<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Pricing;

use App\Entity\Carrier;
use App\Repository\ServiceAreaRepository;
use App\Service\OrderOffer\Pricing\DTO\FreightResolutionResult;
use App\Service\OrderOffer\Pricing\DTO\PricingCalculationContext;

/**
 * Legacy: base freight from drop-off zone matrix by total weight.
 */
final class FlatByDropOffZonePricingStrategy implements PricingStrategyInterface
{
    public function __construct(
        private readonly ServiceAreaRepository $serviceAreaRepository,
        private readonly MatrixItemPriceResolver $matrixItemPriceResolver,
    ) {
    }

    public function resolveBaseFreight(PricingCalculationContext $context): FreightResolutionResult
    {
        $serviceArea = $this->serviceAreaRepository->findByCoordinates(
            $context->dropoutLatitude,
            $context->dropoutLongitude,
            $context->carrier,
        );

        if ($serviceArea === null) {
            return FreightResolutionResult::fail(
                'order_offer.error.service_area_not_found',
                'SERVICE_AREA_NOT_FOUND',
            );
        }

        $price = $this->matrixItemPriceResolver->resolvePriceCents($serviceArea, $context->totalWeightKg);
        if ($price === null) {
            return FreightResolutionResult::fail(
                'order_offer.error.matrix_item_not_found',
                'MATRIX_ITEM_NOT_FOUND',
            );
        }

        return FreightResolutionResult::ok($price, $serviceArea);
    }
}

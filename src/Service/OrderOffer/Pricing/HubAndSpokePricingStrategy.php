<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Pricing;

use App\Entity\Carrier;
use App\Entity\ServiceArea;
use App\Repository\ServiceAreaRepository;
use App\Service\OrderOffer\Pricing\DTO\FreightResolutionResult;
use App\Service\OrderOffer\Pricing\DTO\PricingCalculationContext;

/**
 * Hub-and-spoke pricing for a carrier: prices relative to country home zone.
 */
final class HubAndSpokePricingStrategy implements PricingStrategyInterface
{
    public function __construct(
        private readonly ServiceAreaRepository $serviceAreaRepository,
        private readonly MatrixItemPriceResolver $matrixItemPriceResolver,
    ) {
    }

    public function resolveBaseFreight(PricingCalculationContext $context): FreightResolutionResult
    {
        $carrier = $context->carrier;
        if (!$carrier instanceof Carrier) {
            return FreightResolutionResult::fail(
                'order_offer.error.carrier_required_for_hub_pricing',
                'CARRIER_REQUIRED',
            );
        }

        $pickupZone = $this->serviceAreaRepository->findByCoordinates(
            $context->pickupLatitude,
            $context->pickupLongitude,
            $carrier,
        );
        $deliveryZone = $this->serviceAreaRepository->findByCoordinates(
            $context->dropoutLatitude,
            $context->dropoutLongitude,
            $carrier,
        );

        if ($pickupZone === null || $deliveryZone === null) {
            return FreightResolutionResult::fail(
                'order_offer.error.out_of_coverage',
                'OUT_OF_COVERAGE',
            );
        }

        if ($pickupZone->getCountry() !== $deliveryZone->getCountry()) {
            return FreightResolutionResult::fail(
                'order_offer.error.cross_country_not_supported',
                'CROSS_COUNTRY_NOT_SUPPORTED',
            );
        }

        $country = $pickupZone->getCountry();
        $homeZone = $this->serviceAreaRepository->findHomeZoneForCarrier($carrier, $country);
        if ($homeZone === null) {
            return FreightResolutionResult::fail(
                'order_offer.error.home_zone_not_configured',
                'HOME_ZONE_NOT_CONFIGURED',
            );
        }

        $weight = $context->totalWeightKg;

        if ($this->isSameZone($pickupZone, $deliveryZone)) {
            $base = $this->priceForZone($pickupZone, $weight);
            if ($base === null) {
                return FreightResolutionResult::fail(
                    'order_offer.error.matrix_item_not_found',
                    'MATRIX_ITEM_NOT_FOUND',
                );
            }

            return FreightResolutionResult::ok($base, $deliveryZone);
        }

        if ($this->isSameZone($deliveryZone, $homeZone)) {
            $base = $this->priceForZone($pickupZone, $weight);
            if ($base === null) {
                return FreightResolutionResult::fail(
                    'order_offer.error.matrix_item_not_found',
                    'MATRIX_ITEM_NOT_FOUND',
                );
            }

            return FreightResolutionResult::ok($base, $deliveryZone);
        }

        if ($this->isSameZone($pickupZone, $homeZone)) {
            $base = $this->priceForZone($deliveryZone, $weight);
            if ($base === null) {
                return FreightResolutionResult::fail(
                    'order_offer.error.matrix_item_not_found',
                    'MATRIX_ITEM_NOT_FOUND',
                );
            }

            return FreightResolutionResult::ok($base, $deliveryZone);
        }

        $fromHub = $this->priceForZone($pickupZone, $weight);
        $toHub = $this->priceForZone($deliveryZone, $weight);
        if ($fromHub === null || $toHub === null) {
            return FreightResolutionResult::fail(
                'order_offer.error.matrix_item_not_found',
                'MATRIX_ITEM_NOT_FOUND',
            );
        }

        return FreightResolutionResult::ok($fromHub + $toHub, $deliveryZone);
    }

    private function isSameZone(ServiceArea $a, ServiceArea $b): bool
    {
        $idA = $a->getId();
        $idB = $b->getId();

        return $idA !== null && $idB !== null && $idA->equals($idB);
    }

    private function priceForZone(ServiceArea $zone, int $weightKg): ?int
    {
        return $this->matrixItemPriceResolver->resolvePriceCents($zone, $weightKg);
    }
}

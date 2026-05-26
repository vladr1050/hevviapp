<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Pricing;

use App\Entity\Carrier;
use App\Entity\GeoArea;
use App\Entity\ServiceArea;
use App\Repository\ServiceAreaRepository;
use App\Service\OrderOffer\Pricing\DTO\CoordinateCoverageResult;
use App\Service\OrderOffer\Pricing\DTO\FreightResolutionResult;
use App\Service\OrderOffer\Pricing\DTO\PricingCalculationContext;

/**
 * Hub-and-spoke pricing for a carrier.
 *
 * Intra-city: pickup and drop-off fall in the same GeoArea (one matrix price).
 * Hub legs: different GeoAreas — price from pickup/delivery ServiceArea matrices,
 * with home ServiceArea (isHomeZone) as the logical hub for X↔Y outside the hub.
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

        $pickupCoverage = $this->serviceAreaRepository->findCoverageAtCoordinates(
            $context->pickupLatitude,
            $context->pickupLongitude,
            $carrier,
        );
        $deliveryCoverage = $this->serviceAreaRepository->findCoverageAtCoordinates(
            $context->dropoutLatitude,
            $context->dropoutLongitude,
            $carrier,
        );

        if ($pickupCoverage === null || $deliveryCoverage === null) {
            return FreightResolutionResult::fail(
                'order_offer.error.out_of_coverage',
                'OUT_OF_COVERAGE',
            );
        }

        $pickupZone = $pickupCoverage->serviceArea;
        $deliveryZone = $deliveryCoverage->serviceArea;

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

        if ($this->isSameGeoArea($pickupCoverage->geoArea, $deliveryCoverage->geoArea)) {
            $base = $this->priceForZone($pickupZone, $weight);
            if ($base === null) {
                return FreightResolutionResult::fail(
                    'order_offer.error.matrix_item_not_found',
                    'MATRIX_ITEM_NOT_FOUND',
                );
            }

            return FreightResolutionResult::ok($base, $deliveryZone);
        }

        if ($this->isSameServiceArea($deliveryZone, $homeZone)) {
            $base = $this->priceForZone($pickupZone, $weight);
            if ($base === null) {
                return FreightResolutionResult::fail(
                    'order_offer.error.matrix_item_not_found',
                    'MATRIX_ITEM_NOT_FOUND',
                );
            }

            return FreightResolutionResult::ok($base, $deliveryZone);
        }

        if ($this->isSameServiceArea($pickupZone, $homeZone)) {
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

    private function isSameGeoArea(GeoArea $a, GeoArea $b): bool
    {
        $idA = $a->getId();
        $idB = $b->getId();

        return $idA !== null && $idB !== null && $idA->equals($idB);
    }

    private function isSameServiceArea(ServiceArea $a, ServiceArea $b): bool
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

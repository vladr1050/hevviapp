<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace App\Service\OrderOffer;

use App\Entity\Cargo;
use App\Entity\Order;
use App\Enum\PricingAlgorithm;
use App\Service\OrderOffer\Contract\OrderOfferCalculatorInterface;
use App\Service\OrderOffer\DTO\OrderOfferCalculationResultDto;
use App\Service\OrderOffer\Oversized\OversizedPricingWeightResolver;
use App\Service\OrderOffer\Pricing\DTO\PricingCalculationContext;
use App\Service\OrderOffer\Pricing\PriceCoefficientResolver;
use App\Service\OrderOffer\Pricing\PricingAmountBuilder;
use App\Service\OrderOffer\Pricing\PricingCarrierResolver;
use App\Service\OrderOffer\Pricing\PricingStrategyRegistry;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates freight pricing: carrier + strategy → base freight → coefficient → fee → VAT → brutto.
 */
final class OrderOfferCalculatorService implements OrderOfferCalculatorInterface
{
    public function __construct(
        private readonly PricingCarrierResolver $pricingCarrierResolver,
        private readonly PricingStrategyRegistry $pricingStrategyRegistry,
        private readonly PriceCoefficientResolver $priceCoefficientResolver,
        private readonly OversizedPricingWeightResolver $oversizedPricingWeightResolver,
        private readonly PricingAmountBuilder $pricingAmountBuilder,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function calculate(Order $order): OrderOfferCalculationResultDto
    {
        try {
            $dropLat = $order->getDropoutLatitude();
            $dropLng = $order->getDropoutLongitude();

            if ($dropLat === null || $dropLng === null || $dropLat === '' || $dropLng === '') {
                $this->logger->warning('Order missing dropout coordinates', [
                    'order_id' => $order->getId()?->toRfc4122(),
                ]);

                return OrderOfferCalculationResultDto::error(
                    errorMessage: 'order_offer.error.missing_coordinates',
                    errorCode: 'MISSING_COORDINATES',
                );
            }

            $pickLat = $order->getPickupLatitude();
            $pickLng = $order->getPickupLongitude();

            $carrier = $this->pricingCarrierResolver->resolveForOrder($order);
            $algorithm = $carrier?->getPricingAlgorithm() ?? PricingAlgorithm::FLAT_BY_DROP_OFF_ZONE;

            if ($algorithm === PricingAlgorithm::HUB_AND_SPOKE
                && ($pickLat === null || $pickLng === null || $pickLat === '' || $pickLng === '')) {
                return OrderOfferCalculationResultDto::error(
                    errorMessage: 'order_offer.error.missing_pickup_coordinates',
                    errorCode: 'MISSING_PICKUP_COORDINATES',
                );
            }

            $totalWeight = $this->calculateTotalWeight($order);
            if ($totalWeight <= 0) {
                return OrderOfferCalculationResultDto::error(
                    errorMessage: 'order_offer.error.no_cargo_weight',
                    errorCode: 'NO_CARGO_WEIGHT',
                );
            }

            // Oversized cargo is priced by pallet footprint area (L × W), not by
            // the weight entered for documents. When present, override the
            // chargeable weight with the fixed weight from the configured tiers.
            $oversized = $this->oversizedPricingWeightResolver->resolve($order);
            if ($oversized->applicable) {
                if ($oversized->errorCode !== null) {
                    $errorMessage = match ($oversized->errorCode) {
                        OversizedPricingWeightResolver::ERROR_HEIGHT_EXCEEDS_MAX => 'order_offer.error.height_exceeds_max',
                        default => 'order_offer.error.oversized_not_priceable',
                    };

                    $this->logger->warning('Oversized cargo pricing precondition failed', [
                        'order_id' => $order->getId()?->toRfc4122(),
                        'total_pallets' => $oversized->totalPallets,
                        'error_code' => $oversized->errorCode,
                    ]);

                    return OrderOfferCalculationResultDto::error(
                        errorMessage: $errorMessage,
                        errorCode: $oversized->errorCode,
                    );
                }

                $totalWeight = (int) $oversized->weightKg;
            }

            $context = new PricingCalculationContext(
                order: $order,
                carrier: $carrier,
                totalWeightKg: $totalWeight,
                pickupLatitude: (float) ($pickLat ?? $dropLat),
                pickupLongitude: (float) ($pickLng ?? $dropLng),
                dropoutLatitude: (float) $dropLat,
                dropoutLongitude: (float) $dropLng,
            );

            $freightResult = $this->pricingStrategyRegistry
                ->get($algorithm)
                ->resolveBaseFreight($context);

            if (!$freightResult->success) {
                $this->logger->warning('Freight resolution failed', [
                    'order_id' => $order->getId()?->toRfc4122(),
                    'algorithm' => $algorithm->value,
                    'error_code' => $freightResult->errorCode,
                ]);

                return OrderOfferCalculationResultDto::error(
                    errorMessage: $freightResult->errorMessage ?? 'order_offer.error.calculation_failed',
                    errorCode: $freightResult->errorCode ?? 'CALCULATION_FAILED',
                );
            }

            $currencyArea = $freightResult->currencyArea;
            if ($currencyArea === null) {
                return OrderOfferCalculationResultDto::error(
                    errorMessage: 'order_offer.error.calculation_failed',
                    errorCode: 'CALCULATION_FAILED',
                );
            }

            $coefficient = $this->priceCoefficientResolver->resolve($carrier, $order->getSender());
            $baseNetto = (int) round((float) $freightResult->baseFreightCents * $coefficient);
            $amounts = $this->pricingAmountBuilder->buildFromBaseFreightCents($baseNetto);
            $feePercent = $amounts->feePercent;
            $feeAmount = $amounts->feeCents;
            $nettoPrice = $amounts->nettoCents;
            $vatRatePercent = $amounts->vatPercent;
            $vatAmount = $amounts->vatCents;
            $bruttoPrice = $amounts->bruttoCents;

            $this->logger->info('Successfully calculated order offer', [
                'order_id' => $order->getId()?->toRfc4122(),
                'carrier_id' => $carrier?->getId()?->toRfc4122(),
                'sender_id' => $order->getSender()?->getId()?->toRfc4122(),
                'algorithm' => $algorithm->value,
                'service_area' => $currencyArea->getName(),
                'total_weight' => $totalWeight,
                'oversized' => $oversized->applicable,
                'oversized_pallets' => $oversized->totalPallets,
                'raw_base_freight' => $freightResult->baseFreightCents,
                'coefficient' => $coefficient,
                'base_netto' => $baseNetto,
                'fee_percent' => $feePercent,
                'fee_amount' => $feeAmount,
                'netto_price' => $nettoPrice,
                'vat_percent' => $vatRatePercent,
                'vat_amount' => $vatAmount,
                'brutto_price' => $bruttoPrice,
            ]);

            return OrderOfferCalculationResultDto::success(
                currency: $currencyArea->getCurrency() ?? 'EUR',
                bruttoPrice: $bruttoPrice,
                nettoPrice: $nettoPrice,
                vatPercent: $vatRatePercent,
                vatAmount: $vatAmount,
                feePercent: $feePercent,
                feeAmount: $feeAmount,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Error calculating order offer', [
                'order_id' => $order->getId()?->toRfc4122(),
                'exception' => $e->getMessage(),
            ]);

            return OrderOfferCalculationResultDto::error(
                errorMessage: 'order_offer.error.calculation_failed',
                errorCode: 'CALCULATION_FAILED',
            );
        }
    }

    private function calculateTotalWeight(Order $order): int
    {
        $totalWeight = 0;

        /** @var Cargo $cargo */
        foreach ($order->getCargo() as $cargo) {
            $quantity = $cargo->getQuantity() ?? 0;
            $weight = $cargo->getWeightKg() ?? 0;
            $totalWeight += $quantity * $weight;
        }

        return $totalWeight;
    }
}

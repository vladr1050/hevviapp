<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Oversized;

use App\Entity\Cargo;
use App\Entity\Order;
use App\Repository\OversizedWeightTierRepository;

/**
 * Decides which weight the pricing pipeline should use for an order.
 *
 * When any cargo line is oversized, the whole order switches to "pallet place"
 * mode: every line contributes pallet places (normal pallet = 1 place per unit,
 * oversized = N places per unit, N from {@see OversizedCargoCalculator}), the
 * places are summed across the order, and a single fixed weight is looked up by
 * the total in {@see OversizedWeightTierRepository}.
 *
 * The sender's entered weight is never touched here — documents keep using it.
 */
final class OversizedPricingWeightResolver
{
    public const string ERROR_NOT_PRICEABLE = 'OVERSIZED_NOT_PRICEABLE';

    public const string ERROR_HEIGHT_EXCEEDS_MAX = 'HEIGHT_EXCEEDS_MAX';

    public function __construct(
        private readonly OversizedCargoCalculator $calculator,
        private readonly OversizedWeightTierRepository $tierRepository,
    ) {
    }

    public function resolve(Order $order): OversizedWeightResolution
    {
        $hasOversized = false;
        $totalPallets = 0;

        /** @var Cargo $cargo */
        foreach ($order->getCargo() as $cargo) {
            $quantity = max(0, $cargo->getQuantity() ?? 0);
            if ($quantity === 0) {
                continue;
            }

            $dimensions = $cargo->getDimensionsCm();

            if ($this->calculator->exceedsMaxAllowedHeight($dimensions)) {
                return OversizedWeightResolution::error(self::ERROR_HEIGHT_EXCEEDS_MAX, 0);
            }

            if ($this->calculator->isOversized($dimensions)) {
                $hasOversized = true;
                $placesPerUnit = $this->calculator->palletPlacesForUnit($dimensions);
            } else {
                $placesPerUnit = 1;
            }

            $totalPallets += $placesPerUnit * $quantity;
        }

        if (!$hasOversized) {
            return OversizedWeightResolution::notApplicable();
        }

        $tier = $this->tierRepository->findOneByPallets($totalPallets);
        if ($tier === null) {
            return OversizedWeightResolution::error(self::ERROR_NOT_PRICEABLE, $totalPallets);
        }

        return OversizedWeightResolution::resolved($tier->getWeightKg(), $totalPallets);
    }
}

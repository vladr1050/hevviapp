<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Pricing;

use App\Entity\Carrier;
use App\Entity\Order;
use App\Repository\CarrierRepository;

final class PricingCarrierResolver
{
    public function __construct(
        private readonly CarrierRepository $carrierRepository,
    ) {
    }

    public function resolveForOrder(Order $order): ?Carrier
    {
        $assigned = $order->getCarrier();
        if ($assigned instanceof Carrier) {
            return $assigned;
        }

        return $this->carrierRepository->findDefaultForPricing();
    }
}

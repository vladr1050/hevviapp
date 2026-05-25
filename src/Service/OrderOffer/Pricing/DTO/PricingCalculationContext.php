<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Pricing\DTO;

use App\Entity\Carrier;
use App\Entity\Order;

final readonly class PricingCalculationContext
{
    public function __construct(
        public Order $order,
        public ?Carrier $carrier,
        public int $totalWeightKg,
        public float $pickupLatitude,
        public float $pickupLongitude,
        public float $dropoutLatitude,
        public float $dropoutLongitude,
    ) {
    }
}

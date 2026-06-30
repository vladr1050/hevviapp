<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Pricing\DTO;

/**
 * Offer amounts derived from base freight (net, before platform fee).
 */
final readonly class PricingAmounts
{
    public function __construct(
        public int $baseFreightCents,
        public int $feeCents,
        public int $nettoCents,
        public int $vatCents,
        public int $bruttoCents,
        public float $feePercent,
        public float $vatPercent,
    ) {
    }
}

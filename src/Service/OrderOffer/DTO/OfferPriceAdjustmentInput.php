<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\DTO;

use App\Enum\OfferPriceAdjustmentMode;

final readonly class OfferPriceAdjustmentInput
{
    public function __construct(
        public OfferPriceAdjustmentMode $mode,
        /** Euros for target/delta modes; percent value for percent mode. */
        public float $numericValue,
        public string $reason,
    ) {
    }
}

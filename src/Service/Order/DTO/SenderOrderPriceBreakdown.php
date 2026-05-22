<?php

declare(strict_types=1);

namespace App\Service\Order\DTO;

/**
 * Sender-facing delivery price: freight (carrier VAT) + platform fee (issuer VAT).
 */
final readonly class SenderOrderPriceBreakdown
{
    public function __construct(
        public int $freightNetCents,
        public float $freightVatPercent,
        public string $freightVatPercentLabel,
        public int $freightVatCents,
        public int $freightGrossCents,
        public float $platformFeePercent,
        public string $platformFeePercentLabel,
        public int $platformFeeNetCents,
        public float $platformVatPercent,
        public string $platformVatPercentLabel,
        public int $platformVatCents,
        public int $platformGrossCents,
        public int $senderTotalGrossCents,
        public ?string $carrierLabel = null,
        public ?string $issuerLabel = null,
        public bool $freightVatFromCarrierProfile = false,
    ) {
    }
}

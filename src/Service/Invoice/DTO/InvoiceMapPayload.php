<?php

declare(strict_types=1);

namespace App\Service\Invoice\DTO;

/**
 * Map tile + pin positions (px) inside the invoice map frame (inner area below border).
 */
final readonly class InvoiceMapPayload
{
    public function __construct(
        public string $imageDataUri,
        public bool $showPins,
        public ?string $pickupLeftPx = null,
        public ?string $pickupTopPx = null,
        public ?string $dropLeftPx = null,
        public ?string $dropTopPx = null,
    ) {
    }
}

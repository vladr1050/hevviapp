<?php

declare(strict_types=1);

namespace App\Service\Invoice\DTO;

/**
 * Map tile(s) + pin positions (px) inside the invoice map frame (inner area below border).
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
        /** Inner map box size in CSS px (matches .map-box-inner; SVG viewBox). */
        public ?string $innerWidthPx = null,
        public ?string $innerHeightPx = null,
        /** Scaled strip position (pan + scale so both stops stay inside with padding, midpoint centered when possible). */
        public ?string $mapImgLeftPx = null,
        public ?string $mapImgTopPx = null,
        public ?string $mapImgWidthPx = null,
        public ?string $mapImgHeightPx = null,
        /** Second OSM tile (right neighbour or bottom), for 2×1 / 1×2 strips — empty when single tile. */
        public ?string $secondImageDataUri = null,
        public int $stripTilesX = 1,
        public int $stripTilesY = 1,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Oversized;

/**
 * Outcome of resolving the chargeable weight for an order that contains
 * oversized cargo.
 *
 * - applicable=false  → order has no oversized cargo; use the standard
 *                       (sum of quantity × entered weight) pricing weight.
 * - applicable=true, errorCode=null → use {@see $weightKg} as the pricing weight.
 * - applicable=true, errorCode!=null → the occupied pallet count has no
 *                       configured tier; the order cannot be auto-priced.
 */
final readonly class OversizedWeightResolution
{
    private function __construct(
        public bool $applicable,
        public ?int $weightKg = null,
        public ?int $totalPallets = null,
        public ?string $errorCode = null,
    ) {
    }

    public static function notApplicable(): self
    {
        return new self(applicable: false);
    }

    public static function resolved(int $weightKg, int $totalPallets): self
    {
        return new self(applicable: true, weightKg: $weightKg, totalPallets: $totalPallets);
    }

    public static function error(string $errorCode, int $totalPallets): self
    {
        return new self(applicable: true, totalPallets: $totalPallets, errorCode: $errorCode);
    }
}

<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Pricing\DTO;

use App\Entity\ServiceArea;

final readonly class FreightResolutionResult
{
    public function __construct(
        public bool $success,
        public ?int $baseFreightCents = null,
        public ?ServiceArea $currencyArea = null,
        public ?string $errorMessage = null,
        public ?string $errorCode = null,
    ) {
    }

    public static function ok(int $baseFreightCents, ServiceArea $currencyArea): self
    {
        return new self(
            success: true,
            baseFreightCents: $baseFreightCents,
            currencyArea: $currencyArea,
        );
    }

    public static function fail(string $errorMessage, string $errorCode): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
        );
    }
}

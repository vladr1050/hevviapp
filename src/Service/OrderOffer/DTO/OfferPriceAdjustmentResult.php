<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\DTO;

use App\Entity\OrderOffer;

final readonly class OfferPriceAdjustmentResult
{
    private function __construct(
        public bool $success,
        public ?OrderOffer $newOffer = null,
        public ?OrderOffer $previousOffer = null,
        public ?int $previousSenderTotalCents = null,
        public ?int $newSenderTotalCents = null,
        public ?string $errorMessage = null,
        public ?string $errorCode = null,
    ) {
    }

    public static function success(
        OrderOffer $newOffer,
        OrderOffer $previousOffer,
        int $previousSenderTotalCents,
        int $newSenderTotalCents,
    ): self {
        return new self(
            success: true,
            newOffer: $newOffer,
            previousOffer: $previousOffer,
            previousSenderTotalCents: $previousSenderTotalCents,
            newSenderTotalCents: $newSenderTotalCents,
        );
    }

    public static function error(string $errorMessage, string $errorCode): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
        );
    }
}

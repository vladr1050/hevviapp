<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Pricing;

use App\Entity\Carrier;
use App\Entity\User;
use App\Repository\PricingSettingsRepository;

final class PriceCoefficientResolver
{
    public function __construct(
        private readonly PricingSettingsRepository $pricingSettingsRepository,
    ) {
    }

    public function resolve(?Carrier $carrier, ?User $sender = null): float
    {
        if ($sender === null) {
            return $this->resolveCarrierOnly($carrier);
        }

        $global = $this->getGlobalCoefficient();
        $carrierLocal = $this->extractLocalCoefficient($carrier?->getPriceCoefficient());
        $senderLocal = $this->extractLocalCoefficient($sender->getPriceCoefficient());

        if ($carrierLocal !== null && $senderLocal !== null) {
            return $this->normalizeCoefficient($carrierLocal * $senderLocal);
        }

        if ($carrierLocal !== null) {
            return $this->normalizeCoefficient($carrierLocal);
        }

        if ($senderLocal !== null) {
            return $this->normalizeCoefficient($senderLocal);
        }

        return $this->normalizeCoefficient($global);
    }

    private function resolveCarrierOnly(?Carrier $carrier): float
    {
        $local = $this->extractLocalCoefficient($carrier?->getPriceCoefficient());
        if ($local !== null) {
            return $this->normalizeCoefficient($local);
        }

        return $this->normalizeCoefficient($this->getGlobalCoefficient());
    }

    private function getGlobalCoefficient(): float
    {
        $settings = $this->pricingSettingsRepository->getSingleton();
        $global = $settings?->getDefaultPriceCoefficient() ?? '1.0000';

        return (float) $global;
    }

    private function extractLocalCoefficient(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return (float) $value;
    }

    private function normalizeCoefficient(float $value): float
    {
        if ($value <= 0.0) {
            return 1.0;
        }

        return $value;
    }
}

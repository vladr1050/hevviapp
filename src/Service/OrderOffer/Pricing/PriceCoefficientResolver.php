<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Pricing;

use App\Entity\Carrier;
use App\Repository\PricingSettingsRepository;

final class PriceCoefficientResolver
{
    public function __construct(
        private readonly PricingSettingsRepository $pricingSettingsRepository,
    ) {
    }

    public function resolve(?Carrier $carrier): float
    {
        if ($carrier !== null) {
            $local = $carrier->getPriceCoefficient();
            if ($local !== null && trim($local) !== '') {
                return $this->normalizeCoefficient((float) $local);
            }
        }

        $settings = $this->pricingSettingsRepository->getSingleton();
        $global = $settings?->getDefaultPriceCoefficient() ?? '1.0000';

        return $this->normalizeCoefficient((float) $global);
    }

    private function normalizeCoefficient(float $value): float
    {
        if ($value <= 0.0) {
            return 1.0;
        }

        return $value;
    }
}

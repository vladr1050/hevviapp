<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Pricing;

use App\Service\Billing\IssuingCompanyResolver;
use App\Service\OrderOffer\Pricing\DTO\PricingAmounts;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds stored offer amounts from base freight using the same fee/VAT rules as auto-calculation.
 */
final class PricingAmountBuilder
{
    public function __construct(
        private readonly IssuingCompanyResolver $issuingCompanyResolver,
        #[Autowire('%env(int:TAX_VAT)%')]
        private readonly int $defaultVatPercent,
        #[Autowire('%env(int:PLATFORM_FEE_PERCENT)%')]
        private readonly int $defaultPlatformFeePercent,
    ) {
    }

    public function buildFromBaseFreightCents(int $baseFreightCents): PricingAmounts
    {
        $baseFreightCents = max(1, $baseFreightCents);
        $feePercent = $this->resolvePlatformFeePercent();
        $feeAmount = (int) round($baseFreightCents * $feePercent / 100.0);
        $nettoPrice = $baseFreightCents + $feeAmount;
        $vatRatePercent = $this->resolveVatRatePercent();
        $vatAmount = (int) round($nettoPrice * $vatRatePercent / 100.0);
        $bruttoPrice = $nettoPrice + $vatAmount;

        return new PricingAmounts(
            baseFreightCents: $baseFreightCents,
            feeCents: $feeAmount,
            nettoCents: $nettoPrice,
            vatCents: $vatAmount,
            bruttoCents: $bruttoPrice,
            feePercent: $feePercent,
            vatPercent: $vatRatePercent,
        );
    }

    private function resolvePlatformFeePercent(): float
    {
        $issuer = $this->issuingCompanyResolver->getIssuingCompany();
        if ($issuer !== null) {
            $p = $issuer->getPlatformFeePercent();
            if ($p !== null && $p !== '') {
                return (float) $p;
            }
        }

        return (float) $this->defaultPlatformFeePercent;
    }

    private function resolveVatRatePercent(): float
    {
        $issuer = $this->issuingCompanyResolver->getIssuingCompany();
        if ($issuer !== null) {
            $rate = $issuer->getVatRate();
            if ($rate !== null && $rate !== '') {
                return (float) $rate;
            }
        }

        return (float) $this->defaultVatPercent;
    }
}

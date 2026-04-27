<?php

declare(strict_types=1);

namespace App\Service\Order;

use App\Entity\OrderOffer;
use App\Service\Billing\IssuingCompanyResolver;

/**
 * Sender-facing payable total: (freight net + VAT on freight) + (platform fee + VAT on fee).
 * Freight VAT is fixed until carrier-specific rate is wired; platform VAT follows issuing BillingCompany.
 */
final class SenderOrderPayableTotalCentsCalculator
{
    private const FREIGHT_VAT_PERCENT = 21.0;

    public function __construct(
        private readonly IssuingCompanyResolver $issuingCompanyResolver,
    ) {
    }

    public function computePayableGrossCents(?OrderOffer $offer): ?int
    {
        $parts = $this->computePayableGrossAndVatCents($offer);
        if ($parts === null) {
            return null;
        }

        return $parts['gross_cents'];
    }

    /**
     * @return array{gross_cents: int, vat_cents: int}|null
     */
    public function computePayableGrossAndVatCents(?OrderOffer $offer): ?array
    {
        if ($offer === null) {
            return null;
        }

        $baseCents = $this->resolveBaseFreightCents($offer);
        if ($baseCents === null) {
            return null;
        }

        $feeCents = (int) ($offer->getFee() ?? 0);
        $issuerVatPercent = $this->resolveIssuerVatPercent();

        $freightVatCents = (int) round($baseCents * self::FREIGHT_VAT_PERCENT / 100.0);
        $platformVatCents = (int) round($feeCents * $issuerVatPercent / 100.0);
        $vatCents = $freightVatCents + $platformVatCents;
        $grossCents = $baseCents + $feeCents + $vatCents;

        return ['gross_cents' => $grossCents, 'vat_cents' => $vatCents];
    }

    /**
     * Carrier UI: VAT only on base freight; gross = base + that VAT (no platform fee).
     *
     * @return array{vat_cents: int, gross_cents: int}|null
     */
    public function computeCarrierFreightOnlyVatAndGrossCents(?OrderOffer $offer): ?array
    {
        if ($offer === null) {
            return null;
        }

        $baseCents = $this->resolveBaseFreightCents($offer);
        if ($baseCents === null) {
            return null;
        }

        $vatCents = (int) round($baseCents * self::FREIGHT_VAT_PERCENT / 100.0);

        return ['vat_cents' => $vatCents, 'gross_cents' => $baseCents + $vatCents];
    }

    private function resolveBaseFreightCents(OrderOffer $offer): ?int
    {
        $netto = $offer->getNetto();
        if ($netto === null) {
            return null;
        }
        $fee = $offer->getFee();

        return $fee !== null ? $netto - $fee : $netto;
    }

    private function resolveIssuerVatPercent(): float
    {
        $issuer = $this->issuingCompanyResolver->getIssuingCompany();
        if ($issuer === null) {
            return 0.0;
        }
        $rate = $issuer->getVatRate();
        if ($rate === null || trim((string) $rate) === '') {
            return 0.0;
        }

        return (float) $rate;
    }
}

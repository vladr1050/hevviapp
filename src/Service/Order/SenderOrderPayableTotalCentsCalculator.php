<?php

declare(strict_types=1);

namespace App\Service\Order;

use App\Entity\Carrier;
use App\Entity\Order;
use App\Entity\OrderOffer;
use App\Service\Billing\IssuingCompanyResolver;
use App\Service\Order\DTO\SenderOrderPriceBreakdown;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sender-facing payable total: (freight net + VAT on freight) + (platform fee + VAT on fee).
 * Freight VAT from assigned carrier profile when available; platform fee % and VAT from issuing BillingCompany.
 */
final class SenderOrderPayableTotalCentsCalculator
{
    public function __construct(
        private readonly IssuingCompanyResolver $issuingCompanyResolver,
        #[Autowire('%env(int:TAX_VAT)%')]
        private readonly int $defaultVatPercent,
        #[Autowire('%env(int:PLATFORM_FEE_PERCENT)%')]
        private readonly int $defaultPlatformFeePercent,
    ) {
    }

    public function buildBreakdown(?Order $order, ?OrderOffer $offer): ?SenderOrderPriceBreakdown
    {
        if ($offer === null) {
            return null;
        }

        $baseCents = $this->resolveBaseFreightCents($offer);
        if ($baseCents === null) {
            return null;
        }

        $feeCents = (int) ($offer->getFee() ?? 0);
        $issuer = $this->issuingCompanyResolver->getIssuingCompany();

        [$freightVatPercent, $freightVatFromCarrier] = $this->resolveFreightVatPercent($order);
        $platformFeePercent = $this->resolvePlatformFeePercent($issuer);
        $platformVatPercent = $this->resolveIssuerVatPercent($issuer);

        $freightVatCents = (int) round($baseCents * $freightVatPercent / 100.0);
        $freightGrossCents = $baseCents + $freightVatCents;

        $platformVatCents = (int) round($feeCents * $platformVatPercent / 100.0);
        $platformGrossCents = $feeCents + $platformVatCents;

        $senderTotalGrossCents = $freightGrossCents + $platformGrossCents;

        $carrier = $order?->getCarrier();

        return new SenderOrderPriceBreakdown(
            freightNetCents: $baseCents,
            freightVatPercent: $freightVatPercent,
            freightVatPercentLabel: $this->formatPercentLabel($freightVatPercent),
            freightVatCents: $freightVatCents,
            freightGrossCents: $freightGrossCents,
            platformFeePercent: $platformFeePercent,
            platformFeePercentLabel: $this->formatPercentLabel($platformFeePercent),
            platformFeeNetCents: $feeCents,
            platformVatPercent: $platformVatPercent,
            platformVatPercentLabel: $this->formatPercentLabel($platformVatPercent),
            platformVatCents: $platformVatCents,
            platformGrossCents: $platformGrossCents,
            senderTotalGrossCents: $senderTotalGrossCents,
            carrierLabel: $carrier?->getLegalName(),
            issuerLabel: $issuer?->getName(),
            freightVatFromCarrierProfile: $freightVatFromCarrier,
        );
    }

    public function computePayableGrossCents(?OrderOffer $offer, ?Order $order = null): ?int
    {
        $breakdown = $this->buildBreakdown($order, $offer);

        return $breakdown?->senderTotalGrossCents;
    }

    /**
     * @return array{gross_cents: int, vat_cents: int}|null
     */
    public function computePayableGrossAndVatCents(?OrderOffer $offer, ?Order $order = null): ?array
    {
        $breakdown = $this->buildBreakdown($order, $offer);
        if ($breakdown === null) {
            return null;
        }

        return [
            'gross_cents' => $breakdown->senderTotalGrossCents,
            'vat_cents' => $breakdown->freightVatCents + $breakdown->platformVatCents,
        ];
    }

    /**
     * Carrier UI: VAT only on base freight; gross = base + that VAT (no platform fee).
     *
     * @return array{vat_cents: int, gross_cents: int}|null
     */
    public function computeCarrierFreightOnlyVatAndGrossCents(?OrderOffer $offer, ?Order $order = null): ?array
    {
        $breakdown = $this->buildBreakdown($order, $offer);
        if ($breakdown === null) {
            return null;
        }

        return [
            'vat_cents' => $breakdown->freightVatCents,
            'gross_cents' => $breakdown->freightGrossCents,
        ];
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

    /**
     * @return array{0: float, 1: bool} [percent, fromCarrierProfile]
     */
    private function resolveFreightVatPercent(?Order $order): array
    {
        $carrier = $order?->getCarrier();
        if ($carrier instanceof Carrier) {
            $fromProfile = $this->percentFromNullableString($carrier->getVatRate());
            if ($fromProfile !== null) {
                return [$fromProfile, true];
            }
        }

        return [(float) $this->defaultVatPercent, false];
    }

    private function resolvePlatformFeePercent(?\App\Entity\BillingCompany $issuer): float
    {
        if ($issuer !== null) {
            $p = $issuer->getPlatformFeePercent();
            if ($p !== null && trim((string) $p) !== '') {
                return (float) $p;
            }
        }

        return (float) $this->defaultPlatformFeePercent;
    }

    private function resolveIssuerVatPercent(?\App\Entity\BillingCompany $issuer): float
    {
        if ($issuer !== null) {
            $fromIssuer = $this->percentFromNullableString($issuer->getVatRate());
            if ($fromIssuer !== null) {
                return $fromIssuer;
            }
        }

        return (float) $this->defaultVatPercent;
    }

    private function percentFromNullableString(?string $raw): ?float
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $rate = (float) $raw;
        if ($rate < 0.0 || $rate > 100.0) {
            return null;
        }

        return $rate;
    }

    private function formatPercentLabel(float $percent): string
    {
        $rounded = round($percent, 4);
        if (abs($rounded - round($rounded)) < 0.0001) {
            return sprintf('%d%%', (int) round($rounded));
        }

        return rtrim(rtrim(sprintf('%.2f', $rounded), '0'), '.').'%';
    }
}

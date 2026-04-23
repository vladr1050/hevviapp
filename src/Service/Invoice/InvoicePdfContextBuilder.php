<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Entity\BillingCompany;
use App\Entity\Carrier;
use App\Entity\Invoice;
use App\Entity\User;
use App\Service\Invoice\DTO\InvoiceMapPayload;

/**
 * Twig variables for invoice/pdf.html.twig (all document_type variants).
 */
final class InvoicePdfContextBuilder
{
    public function __construct(
        private readonly InvoiceMoneyFormatter $moneyFormatter,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(
        Invoice $invoice,
        InvoiceMapPayload $map,
        ?User $buyerEntity = null,
        ?BillingCompany $issuerEntity = null,
        string $documentType = 'INVOICE',
        ?string $displayInvoiceNumber = null,
        ?Carrier $carrierEntity = null,
    ): array {
        $c = $invoice->getCurrency() ?? 'EUR';
        $number = $displayInvoiceNumber ?? (string) $invoice->getInvoiceNumber();

        return [
            'document_type' => $documentType,
            'invoice_number' => $number,
            'issue_date' => $invoice->getIssueDate()?->format('d.m.Y'),
            'due_date' => $invoice->getDueDate()?->format('d.m.Y'),
            'service_date' => $invoice->getIssueDate()?->format('d.m.Y'),
            'seller_name' => $invoice->getSellerName(),
            'seller_reg' => $invoice->getSellerRegistrationNumber(),
            'seller_vat' => $invoice->getSellerVatNumber() ?? '—',
            'seller_line1' => $invoice->getSellerAddressLine1(),
            'seller_line2' => $invoice->getSellerAddressLine2(),
            'seller_address_lines' => $this->nonEmptyAddressLines([
                $invoice->getSellerAddressLine1(),
                $invoice->getSellerAddressLine2(),
            ]),
            'seller_email' => $invoice->getSellerEmail(),
            'seller_phone' => $invoice->getSellerPhone(),
            'buyer_company' => $invoice->getBuyerCompanyName(),
            'buyer_reg' => $invoice->getBuyerRegistrationNumber(),
            'buyer_vat' => $invoice->getBuyerVatNumber() ?? '—',
            'buyer_address_lines' => array_values(array_filter(explode("\n", $invoice->getBuyerAddress() ?? ''), static fn (string $l): bool => trim($l) !== '')),
            'order_reference' => $invoice->getOrderReference(),
            'vehicle_plate' => $this->lineOrDash($invoice->getRelatedOrder()?->getVehiclePlate()),
            'pickup' => $invoice->getPickupAddress(),
            'delivery' => $invoice->getDeliveryAddress(),
            'map_data_uri' => $map->imageDataUri,
            'map_show_pins' => $map->showPins,
            'map_pickup_left' => $map->pickupLeftPx,
            'map_pickup_top' => $map->pickupTopPx,
            'map_drop_left' => $map->dropLeftPx,
            'map_drop_top' => $map->dropTopPx,
            'map_inner_w' => $map->innerWidthPx,
            'map_inner_h' => $map->innerHeightPx,
            'map_img_left' => $map->mapImgLeftPx,
            'map_img_top' => $map->mapImgTopPx,
            'map_img_w' => $map->mapImgWidthPx,
            'map_img_h' => $map->mapImgHeightPx,
            'map_data_uri_b' => $map->secondImageDataUri,
            'map_strip_tiles_x' => $map->stripTilesX,
            'map_strip_tiles_y' => $map->stripTilesY,
            'amount_freight' => $this->moneyFormatter->formatCents((int) $invoice->getAmountFreight(), $c),
            'amount_commission' => $this->moneyFormatter->formatCents((int) $invoice->getAmountCommission(), $c),
            'amount_subtotal' => $this->moneyFormatter->formatCents((int) $invoice->getAmountSubtotal(), $c),
            'amount_vat' => $this->moneyFormatter->formatCents((int) $invoice->getAmountVat(), $c),
            'amount_gross' => $this->moneyFormatter->formatCents((int) $invoice->getAmountGross(), $c),
            'amount_gross_number' => $this->moneyFormatter->formatCentsNumber((int) $invoice->getAmountGross()),
            'invoice_currency' => $c,
            'fee_percent_label' => $this->formatPercentLabel((string) $invoice->getFeePercent()),
            'vat_percent_label' => $this->formatPercentLabel((string) ($invoice->getVatRatePercent() ?? '0')),
            /** VAT % from issuing legal entity (BillingCompany), for PDF lines that must reflect issuer profile, not only invoice snapshot. */
            'issuer_vat_percent_label' => $this->formatPercentLabel((string) (
                $issuerEntity?->getVatRate() ?? $invoice->getVatRatePercent() ?? '0'
            )),
            'payment_method' => 'Bankas pārskaitījums',
            'payment_due_date' => $invoice->getDueDate()?->format('d.m.Y') ?? '—',
            'seller_iban' => $this->formatOptionalIban($issuerEntity?->getIban()),
            'buyer_iban' => $this->formatOptionalIban($buyerEntity?->getIban()),
            'carrier_company' => $this->lineOrDash($carrierEntity?->getLegalName()),
            'carrier_reg' => $this->lineOrDash($carrierEntity?->getRegistrationNumber()),
            'carrier_vat' => $this->formatOptionalVat($carrierEntity?->getVatNumber()),
            'carrier_address_lines' => $this->addressToLines($carrierEntity?->getAddress()),
        ];
    }

    /**
     * @param list<?string> $parts
     *
     * @return list<string>
     */
    private function nonEmptyAddressLines(array $parts): array
    {
        $lines = array_values(array_filter(
            $parts,
            static fn (?string $l): bool => $l !== null && trim($l) !== ''
        ));

        return $lines !== [] ? $lines : ['—'];
    }

    /**
     * @return list<string>
     */
    private function addressToLines(?string $address): array
    {
        if ($address === null || trim($address) === '') {
            return ['—'];
        }

        return array_values(array_filter(explode("\n", $address), static fn (string $l): bool => trim($l) !== ''));
    }

    private function formatOptionalVat(?string $vat): string
    {
        $t = $vat !== null ? trim($vat) : '';

        return $t !== '' ? $t : '—';
    }

    private function lineOrDash(?string $value): string
    {
        if ($value === null) {
            return '—';
        }
        $t = trim($value);

        return $t !== '' ? $t : '—';
    }

    private function formatOptionalIban(?string $iban): string
    {
        $t = $iban !== null ? trim($iban) : '';

        return $t !== '' ? $t : '—';
    }

    private function formatPercentLabel(string $decimal): string
    {
        $f = (float) $decimal;
        if (abs($f - round($f)) < 0.0001) {
            return (string) (int) round($f);
        }

        return rtrim(rtrim(number_format($f, 2, ',', ''), '0'), ',');
    }
}

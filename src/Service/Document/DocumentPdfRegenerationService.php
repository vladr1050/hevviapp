<?php

declare(strict_types=1);

namespace App\Service\Document;

use App\Entity\BillingCompany;
use App\Entity\Carrier;
use App\Entity\Document;
use App\Entity\Invoice;
use App\Entity\Order;
use App\Entity\OrderHistory;
use App\Entity\User;
use App\Enum\DocumentType;
use App\Repository\BillingCompanyRepository;
use App\Repository\InvoiceRepository;
use App\Service\Invoice\ChromiumInvoicePdfRenderer;
use App\Service\Invoice\InvoiceMoneyFormatter;
use App\Service\Invoice\InvoicePdfContextBuilder;
use App\Service\Invoice\InvoiceStaticMapFetcher;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

/**
 * Re-renders PDF files for existing {@see Document} rows when files were lost on disk.
 */
final class DocumentPdfRegenerationService
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly BillingCompanyRepository $billingCompanyRepository,
        private readonly InvoicePdfContextBuilder $invoicePdfContextBuilder,
        private readonly InvoiceMoneyFormatter $invoiceMoneyFormatter,
        private readonly InvoiceStaticMapFetcher $staticMapFetcher,
        private readonly Environment $twig,
        private readonly ChromiumInvoicePdfRenderer $pdfRenderer,
        private readonly StoredPdfPathResolver $storedPdfPathResolver,
        #[Autowire('%env(int:PLATFORM_FEE_PERCENT)%')]
        private readonly int $defaultPlatformFeePercent,
    ) {
    }

    public function isPdfMissingOnDisk(Document $document): bool
    {
        $relative = $document->getFilePath();
        if ($relative === null || $relative === '') {
            return true;
        }

        return $this->storedPdfPathResolver->resolveReadableFile($relative) === null;
    }

    public function regenerate(Document $document): void
    {
        $relative = $document->getFilePath();
        if ($relative === null || $relative === '') {
            throw new \RuntimeException('Document has no file_path.');
        }

        $twigContext = match ($document->getDocumentType()) {
            DocumentType::PAYMENT_NOTICE => $this->buildPaymentNoticeContext($document),
            DocumentType::CUSTOMER_INVOICE => $this->buildDeliveredDocumentContext($document, DocumentType::CUSTOMER_INVOICE),
            DocumentType::CARRIER_INVOICE => $this->buildDeliveredDocumentContext($document, DocumentType::CARRIER_INVOICE),
        };

        $html = $this->twig->render('invoice/pdf.html.twig', $twigContext);
        $pdfBinary = $this->pdfRenderer->renderHtmlToPdf($html);
        $this->writePdfToRelativePath($relative, $pdfBinary);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPaymentNoticeContext(Document $document): array
    {
        $relative = (string) $document->getFilePath();
        $invoice = $this->invoiceRepository->findOneByPdfRelativePath($relative);
        if ($invoice === null) {
            throw new \RuntimeException(sprintf('No invoice with pdf_relative_path "%s".', $relative));
        }

        $order = $document->getRelatedOrder() ?? $invoice->getRelatedOrder();
        if (!$order instanceof Order) {
            throw new \RuntimeException('Payment notice document has no related order.');
        }

        $sender = $document->getSenderCompany() ?? $order->getSender();
        if (!$sender instanceof User) {
            throw new \RuntimeException('Payment notice document has no sender.');
        }

        $issuer = $document->getReceiverCompany() ?? $this->billingCompanyRepository->findIssuingCompany();
        if (!$issuer instanceof BillingCompany) {
            throw new \RuntimeException('Payment notice document has no issuing company.');
        }

        $mapPayload = $this->staticMapFetcher->fetchMapPayload(
            $order->getPickupLatitude(),
            $order->getPickupLongitude(),
            $order->getDropoutLatitude(),
            $order->getDropoutLongitude(),
        );

        return $this->invoicePdfContextBuilder->build(
            $invoice,
            $mapPayload,
            $sender,
            $issuer,
            DocumentType::PAYMENT_NOTICE->value,
            $document->getDocumentNumber(),
            null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDeliveredDocumentContext(Document $document, DocumentType $type): array
    {
        if ($type !== DocumentType::CUSTOMER_INVOICE && $type !== DocumentType::CARRIER_INVOICE) {
            throw new \InvalidArgumentException('Only delivered invoice document types are supported.');
        }

        $order = $document->getRelatedOrder();
        if (!$order instanceof Order) {
            throw new \RuntimeException('Document has no related order.');
        }

        $carrier = $document->getCarrierCompany() ?? $order->getCarrier();
        if (!$carrier instanceof Carrier) {
            throw new \RuntimeException('Document has no carrier.');
        }

        $invoice = $this->invoiceRepository->findLatestWithPdfForOrder($order);
        if ($invoice === null) {
            throw new \RuntimeException('No invoice with pdf_relative_path for order.');
        }

        $sender = $document->getSenderCompany() ?? $order->getSender();
        if (!$sender instanceof User) {
            throw new \RuntimeException('Document has no sender.');
        }

        $issuer = $document->getReceiverCompany() ?? $this->billingCompanyRepository->findIssuingCompany();
        if (!$issuer instanceof BillingCompany) {
            throw new \RuntimeException('Document has no issuing company.');
        }

        $mapPayload = $this->staticMapFetcher->fetchMapPayload(
            $order->getPickupLatitude(),
            $order->getPickupLongitude(),
            $order->getDropoutLatitude(),
            $order->getDropoutLongitude(),
        );

        $tz = new \DateTimeZone('Europe/Riga');
        $issueDisplay = $document->getIssuedAt()?->setTimezone($tz)->format('d.m.Y')
            ?? (new \DateTimeImmutable('now', $tz))->format('d.m.Y');
        $deliveredDisplay = $order->getDeliveryDate() !== null
            ? $order->getDeliveryDate()->format('d.m.Y')
            : $issueDisplay;
        $customerServiceDateDisplay = $this->resolvePaidStatusFirstAtDisplay($order, $tz, $deliveredDisplay);

        $freight = (int) $invoice->getAmountFreight();
        $feePercent = $this->resolvePlatformFeePercentFloat($issuer);
        $customerNet = (int) round($freight * $feePercent / 100.0);
        $issuerVatPercent = $this->resolveIssuerVatPercentFloat($issuer);
        $customerVat = $this->isIssuerVatRateZero($issuerVatPercent)
            ? 0
            : (int) round($customerNet * $issuerVatPercent / 100.0);
        $customerGross = $customerNet + $customerVat;

        $subtotal = max(1, (int) $invoice->getAmountSubtotal());
        $vatTotal = (int) $invoice->getAmountVat();
        $allocVat = static function (int $part) use ($vatTotal, $subtotal): int {
            return (int) round($vatTotal * ($part / $subtotal));
        };

        $carrierNet = $freight;
        $carrierVatFromProfile = $this->carrierVatCentsFromProfile($carrier, $carrierNet);
        $carrierVat = $carrierVatFromProfile ?? $allocVat($freight);
        $carrierGross = $carrierNet + $carrierVat;

        if ($type === DocumentType::CUSTOMER_INVOICE) {
            $ctx = $this->invoicePdfContextBuilder->build(
                $invoice,
                $mapPayload,
                $sender,
                $issuer,
                DocumentType::CUSTOMER_INVOICE->value,
                $document->getDocumentNumber(),
                $carrier,
            );
            $ctx['issue_date'] = $issueDisplay;
            $ctx['service_date'] = $customerServiceDateDisplay;

            return $this->applyCustomerDeliveredPdfContext($ctx, $invoice, $customerNet, $customerVat, $customerGross);
        }

        $ctx = $this->invoicePdfContextBuilder->build(
            $invoice,
            $mapPayload,
            $sender,
            $issuer,
            DocumentType::CARRIER_INVOICE->value,
            $document->getDocumentNumber(),
            $carrier,
        );
        $ctx['issue_date'] = $issueDisplay;
        $ctx['service_date'] = $deliveredDisplay;

        return $this->applyCarrierDeliveredPdfContext(
            $ctx,
            $invoice,
            $carrier,
            $order,
            $carrierNet,
            $carrierVat,
            $carrierGross,
            $customerNet,
            $customerVat,
            $customerGross,
            $tz,
        );
    }

    private function writePdfToRelativePath(string $relativePath, string $pdfBinary): void
    {
        $fullPath = $this->storedPdfPathResolver->getPrimaryDir() . '/' . $relativePath;
        $dir = dirname($fullPath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Cannot create directory "%s".', $dir));
        }
        if (file_put_contents($fullPath, $pdfBinary) === false) {
            throw new \RuntimeException(sprintf('Cannot write PDF "%s".', $fullPath));
        }
    }

    /**
     * @param array<string, mixed> $ctx
     *
     * @return array<string, mixed>
     */
    private function applyCustomerDeliveredPdfContext(
        array $ctx,
        Invoice $invoice,
        int $netCents,
        int $vatCents,
        int $grossCents,
    ): array {
        $c = $invoice->getCurrency() ?? 'EUR';
        $ctx['amount_commission'] = $this->invoiceMoneyFormatter->formatCents($netCents, $c);
        $ctx['amount_subtotal'] = $this->invoiceMoneyFormatter->formatCents($netCents, $c);
        $ctx['amount_vat'] = $this->invoiceMoneyFormatter->formatCents($vatCents, $c);
        $ctx['amount_gross'] = $this->invoiceMoneyFormatter->formatCents($grossCents, $c);
        $ctx['amount_gross_number'] = $this->invoiceMoneyFormatter->formatCentsNumber($grossCents);

        return $ctx;
    }

    /**
     * @param array<string, mixed> $ctx
     *
     * @return array<string, mixed>
     */
    private function applyCarrierDeliveredPdfContext(
        array $ctx,
        Invoice $invoice,
        Carrier $carrier,
        Order $order,
        int $carrierNetCents,
        int $carrierVatCents,
        int $carrierGrossCents,
        int $platformNetCents,
        int $platformVatCents,
        int $platformGrossCents,
        \DateTimeZone $tz,
    ): array {
        $c = $invoice->getCurrency() ?? 'EUR';
        $ctx['amount_freight'] = $this->invoiceMoneyFormatter->formatCents($carrierNetCents, $c);
        $ctx['amount_subtotal'] = $this->invoiceMoneyFormatter->formatCents($carrierNetCents, $c);
        $ctx['amount_vat'] = $this->invoiceMoneyFormatter->formatCents($carrierVatCents, $c);
        $ctx['amount_gross'] = $this->invoiceMoneyFormatter->formatCents($carrierGrossCents, $c);
        $ctx['amount_gross_number'] = $this->invoiceMoneyFormatter->formatCentsNumber($carrierGrossCents);
        $ctx['payment_beneficiary'] = $this->carrierPayoutBeneficiaryLine($carrier);
        $ctx['payment_iban'] = $this->optionalIbanLine($carrier->getIban());

        $rateRaw = $carrier->getVatRate();
        if ($rateRaw !== null && trim((string) $rateRaw) !== '') {
            $ctx['vat_percent_label'] = $this->invoicePdfContextBuilder->formatPercentForLabel((string) $rateRaw);
        }

        $ctx['party_carrier_net'] = $this->invoiceMoneyFormatter->formatCents($carrierNetCents, $c);
        $ctx['party_carrier_vat'] = $this->invoiceMoneyFormatter->formatCents($carrierVatCents, $c);
        $ctx['party_carrier_gross'] = $this->invoiceMoneyFormatter->formatCents($carrierGrossCents, $c);
        $ctx['party_platform_net'] = $this->invoiceMoneyFormatter->formatCents($platformNetCents, $c);
        $ctx['party_platform_vat'] = $this->invoiceMoneyFormatter->formatCents($platformVatCents, $c);
        $ctx['party_platform_gross'] = $this->invoiceMoneyFormatter->formatCents($platformGrossCents, $c);

        $invoiceGross = (int) $invoice->getAmountGross();
        $ctx['invoice_total_gross'] = $this->invoiceMoneyFormatter->formatCents($invoiceGross, $c);
        $ctx['invoice_total_gross_number'] = $this->invoiceMoneyFormatter->formatCentsNumber($invoiceGross);

        $ctx['carrier_partner_display'] = sprintf(
            '%s · %s',
            trim((string) ($ctx['carrier_company'] ?? '—')),
            trim((string) ($ctx['carrier_reg'] ?? '—')),
        );
        $ctx['carrier_payment_due_date'] = $this->formatCarrierPaymentDueDate($order, $tz);

        return $ctx;
    }

    private function formatCarrierPaymentDueDate(Order $order, \DateTimeZone $tz): string
    {
        $delivery = $order->getDeliveryDate();
        if ($delivery !== null) {
            $base = \DateTimeImmutable::createFromMutable(clone $delivery)->setTimezone($tz);
        } else {
            $base = new \DateTimeImmutable('now', $tz);
        }

        return $base->modify('+1 day')->format('d.m.Y');
    }

    private function carrierVatCentsFromProfile(Carrier $carrier, int $netCents): ?int
    {
        $raw = $carrier->getVatRate();
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }
        $rate = (float) $raw;
        if ($rate < 0.0 || $rate > 100.0) {
            return null;
        }

        return (int) round($netCents * $rate / 100.0);
    }

    private function carrierPayoutBeneficiaryLine(Carrier $carrier): string
    {
        $holder = $carrier->getBankAccountHolder();
        if ($holder !== null && trim($holder) !== '') {
            return trim($holder);
        }
        $legal = $carrier->getLegalName();

        return ($legal !== null && trim($legal) !== '') ? trim($legal) : '—';
    }

    private function optionalIbanLine(?string $iban): string
    {
        $t = $iban !== null ? trim($iban) : '';

        return $t !== '' ? $t : '—';
    }

    private function resolvePlatformFeePercentFloat(BillingCompany $issuer): float
    {
        $p = $issuer->getPlatformFeePercent();
        if ($p !== null && $p !== '') {
            return (float) $p;
        }

        return (float) $this->defaultPlatformFeePercent;
    }

    private function resolveIssuerVatPercentFloat(BillingCompany $issuer): float
    {
        $rate = $issuer->getVatRate();
        if ($rate === null || trim((string) $rate) === '') {
            return 0.0;
        }

        return (float) $rate;
    }

    private function isIssuerVatRateZero(float $percent): bool
    {
        return abs($percent) < 0.0000001;
    }

    private function resolvePaidStatusFirstAtDisplay(Order $order, \DateTimeZone $tz, string $fallbackDisplay): string
    {
        $earliest = null;
        foreach ($order->getHistories() as $history) {
            if (!$history instanceof OrderHistory) {
                continue;
            }
            if ($history->getStatus() !== Order::STATUS['PAID']) {
                continue;
            }
            $at = $history->getCreatedAt();
            if ($at === null) {
                continue;
            }
            if ($earliest === null || $at < $earliest) {
                $earliest = $at;
            }
        }

        if ($earliest === null) {
            return $fallbackDisplay;
        }

        return $earliest->setTimezone($tz)->format('d.m.Y');
    }
}

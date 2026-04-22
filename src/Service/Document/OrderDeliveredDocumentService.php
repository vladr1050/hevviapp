<?php

declare(strict_types=1);

namespace App\Service\Document;

use App\Entity\BillingCompany;
use App\Entity\Carrier;
use App\Entity\Document;
use App\Entity\Invoice;
use App\Entity\Order;
use App\Entity\User;
use App\Enum\DocumentStatus;
use App\Enum\DocumentType;
use App\Notification\NotificationEventKey;
use App\Repository\BillingCompanyRepository;
use App\Repository\DocumentRepository;
use App\Repository\InvoiceRepository;
use App\Service\Invoice\ChromiumInvoicePdfRenderer;
use App\Service\Invoice\InvoiceMoneyFormatter;
use App\Service\Invoice\InvoicePdfContextBuilder;
use App\Service\Invoice\InvoiceStaticMapFetcher;
use App\Service\Notification\NotificationDispatchService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

/**
 * After delivery: generates customer + carrier PDFs (same Twig as prepayment), persists {@see Document} rows, emails one attachment each.
 */
final class OrderDeliveredDocumentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly BillingCompanyRepository $billingCompanyRepository,
        private readonly DocumentNumberFormatter $documentNumberFormatter,
        private readonly InvoicePdfContextBuilder $invoicePdfContextBuilder,
        private readonly InvoiceMoneyFormatter $invoiceMoneyFormatter,
        private readonly InvoiceStaticMapFetcher $staticMapFetcher,
        private readonly Environment $twig,
        private readonly ChromiumInvoicePdfRenderer $pdfRenderer,
        private readonly StoredPdfPathResolver $storedPdfPathResolver,
        private readonly NotificationDispatchService $notificationDispatchService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function issueForDeliveredOrder(Order $order): void
    {
        if ($order->getStatus() !== Order::STATUS['DELIVERED']) {
            return;
        }

        try {
            $this->doIssue($order);
        } catch (\Throwable $e) {
            $this->logger->error('Delivered documents failed', [
                'order_id' => $order->getId()?->toRfc4122(),
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    private function doIssue(Order $order): void
    {
        $carrier = $order->getCarrier();
        if ($carrier === null) {
            return;
        }

        $invoice = $this->invoiceRepository->findLatestWithPdfForOrder($order);
        if ($invoice === null) {
            return;
        }

        $sender = $order->getSender();
        if (!$sender instanceof User) {
            return;
        }

        $issuer = $this->billingCompanyRepository->findIssuingCompany();
        if (!$issuer instanceof BillingCompany) {
            return;
        }

        $paymentNotice = $this->documentRepository->findOneByOrderAndType($order, DocumentType::PAYMENT_NOTICE);

        $mapPayload = $this->staticMapFetcher->fetchMapPayload(
            $order->getPickupLatitude(),
            $order->getPickupLongitude(),
            $order->getDropoutLatitude(),
            $order->getDropoutLongitude()
        );

        $tz = new \DateTimeZone('Europe/Riga');
        $nowDisplay = (new \DateTimeImmutable('now', $tz))->format('d.m.Y');
        $deliveredDisplay = $order->getDeliveryDate() !== null
            ? $order->getDeliveryDate()->format('d.m.Y')
            : $nowDisplay;

        $subtotal = max(1, (int) $invoice->getAmountSubtotal());
        $vatTotal = (int) $invoice->getAmountVat();
        $freight = (int) $invoice->getAmountFreight();
        $commission = (int) $invoice->getAmountCommission();
        $allocVat = static function (int $part) use ($vatTotal, $subtotal): int {
            return (int) round($vatTotal * ($part / $subtotal));
        };

        $customerNet = $commission;
        $customerVat = $allocVat($commission);
        $customerGross = $customerNet + $customerVat;

        $carrierNet = $freight;
        $carrierVat = $allocVat($freight);
        $carrierGross = $carrierNet + $carrierVat;

        $customerDoc = $this->documentRepository->findOneByOrderAndType($order, DocumentType::CUSTOMER_INVOICE);
        if ($customerDoc === null) {
            $customerNumber = $this->documentNumberFormatter->formatCustomerInvoiceNumber((string) $invoice->getInvoiceNumber());
            $ctx = $this->invoicePdfContextBuilder->build(
                $invoice,
                $mapPayload,
                $sender,
                $issuer,
                DocumentType::CUSTOMER_INVOICE->value,
                $customerNumber,
                $carrier,
            );
            $ctx['issue_date'] = $nowDisplay;
            $ctx['service_date'] = $deliveredDisplay;
            $ctx = $this->applyCustomerDeliveredPdfContext($ctx, $invoice, $customerNet, $customerVat, $customerGross);

            $customerDoc = $this->persistRenderedDocument(
                $order,
                DocumentType::CUSTOMER_INVOICE,
                $customerNumber,
                $paymentNotice,
                $sender,
                $issuer,
                $carrier,
                $customerNet,
                $customerVat,
                $customerGross,
                $ctx,
                $invoice,
            );
        }

        $carrierDoc = $this->documentRepository->findOneByOrderAndType($order, DocumentType::CARRIER_INVOICE);
        if ($carrierDoc === null) {
            $ctx = $this->invoicePdfContextBuilder->build(
                $invoice,
                $mapPayload,
                $sender,
                $issuer,
                DocumentType::CARRIER_INVOICE->value,
                $this->documentNumberFormatter->formatCarrierInvoiceNumber((string) $invoice->getInvoiceNumber()),
                $carrier,
            );
            $ctx['issue_date'] = $nowDisplay;
            $ctx['service_date'] = $deliveredDisplay;
            $ctx = $this->applyCarrierDeliveredPdfContext($ctx, $invoice, $carrier, $carrierNet, $carrierVat, $carrierGross);

            $carrierDoc = $this->persistRenderedDocument(
                $order,
                DocumentType::CARRIER_INVOICE,
                $this->documentNumberFormatter->formatCarrierInvoiceNumber((string) $invoice->getInvoiceNumber()),
                $paymentNotice,
                $sender,
                $issuer,
                $carrier,
                $carrierNet,
                $carrierVat,
                $carrierGross,
                $ctx,
                $invoice,
            );
        }

        $customerDoc ??= $this->documentRepository->findOneByOrderAndType($order, DocumentType::CUSTOMER_INVOICE);
        $carrierDoc ??= $this->documentRepository->findOneByOrderAndType($order, DocumentType::CARRIER_INVOICE);

        if ($customerDoc !== null && $customerDoc->getFilePath() !== null && $customerDoc->getFilePath() !== '') {
            $this->notificationDispatchService->dispatch(
                $order,
                NotificationEventKey::ORDER_DELIVERED_SENDER_DOCUMENT,
                null,
                false,
                $customerDoc,
            );
        }

        if ($carrierDoc !== null && $carrierDoc->getFilePath() !== null && $carrierDoc->getFilePath() !== '') {
            $this->notificationDispatchService->dispatch(
                $order,
                NotificationEventKey::ORDER_DELIVERED_CARRIER_DOCUMENT,
                null,
                false,
                $carrierDoc,
            );
        }
    }

    /**
     * @param array<string, mixed> $twigContext
     */
    private function persistRenderedDocument(
        Order $order,
        DocumentType $type,
        string $documentNumber,
        ?Document $relatedDocument,
        User $sender,
        BillingCompany $issuer,
        Carrier $carrier,
        int $amountNet,
        int $amountVat,
        int $amountTotal,
        array $twigContext,
        Invoice $invoice,
    ): Document {
        $document = new Document();
        $document->setRelatedOrder($order);
        $document->setDocumentType($type);
        $document->setDocumentNumber($documentNumber);
        $document->setRelatedDocument($relatedDocument);
        $document->setSenderCompany($sender);
        $document->setReceiverCompany($issuer);
        $document->setCarrierCompany($carrier);
        $document->setAmountNet($amountNet);
        $document->setAmountVat($amountVat);
        $document->setAmountTotal($amountTotal);
        $document->setStatus(DocumentStatus::GENERATED);

        $this->em->persist($document);
        $this->em->flush();

        $id = $document->getId();
        if (!$id instanceof Uuid) {
            throw new \RuntimeException('Document has no id after persist.');
        }

        $html = $this->twig->render('invoice/pdf.html.twig', $twigContext);
        $pdfBinary = $this->pdfRenderer->renderHtmlToPdf($html);

        $orderUuid = $order->getId()?->toRfc4122();
        if ($orderUuid === null || $orderUuid === '') {
            throw new \RuntimeException('Order has no id for PDF storage path.');
        }

        $relativePath = sprintf(
            '%s/%s-%s.pdf',
            $orderUuid,
            $id->toRfc4122(),
            strtolower($type->value),
        );
        $fullPath = $this->storedPdfPathResolver->getPrimaryDir() . '/' . $relativePath;
        $dir = dirname($fullPath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Cannot create directory "%s".', $dir));
        }
        if (file_put_contents($fullPath, $pdfBinary) === false) {
            throw new \RuntimeException(sprintf('Cannot write PDF "%s".', $fullPath));
        }

        $document->setFilePath($relativePath);
        $this->em->flush();

        return $document;
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
        int $netCents,
        int $vatCents,
        int $grossCents,
    ): array {
        $c = $invoice->getCurrency() ?? 'EUR';
        $ctx['amount_freight'] = $this->invoiceMoneyFormatter->formatCents($netCents, $c);
        $ctx['amount_subtotal'] = $this->invoiceMoneyFormatter->formatCents($netCents, $c);
        $ctx['amount_vat'] = $this->invoiceMoneyFormatter->formatCents($vatCents, $c);
        $ctx['amount_gross'] = $this->invoiceMoneyFormatter->formatCents($grossCents, $c);
        $ctx['amount_gross_number'] = $this->invoiceMoneyFormatter->formatCentsNumber($grossCents);
        $ctx['payment_beneficiary'] = $this->carrierPayoutBeneficiaryLine($carrier);
        $ctx['payment_iban'] = $this->optionalIbanLine($carrier->getIban());

        return $ctx;
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
}

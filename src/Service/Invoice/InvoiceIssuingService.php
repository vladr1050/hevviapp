<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Entity\BillingCompany;
use App\Entity\Document;
use App\Entity\Invoice;
use App\Entity\Order;
use App\Entity\OrderOffer;
use App\Entity\User;
use App\Enum\DocumentStatus;
use App\Enum\DocumentType;
use App\Repository\BillingCompanyRepository;
use App\Repository\DocumentRepository;
use App\Repository\InvoiceRepository;
use App\Service\Document\DocumentNumberFormatter;
use App\Service\Document\StoredPdfPathResolver;
use App\Service\Order\SenderOrderPayableTotalCentsCalculator;
use App\Notification\NotificationEventKey;
use App\Service\Notification\NotificationDispatchService;
use App\Service\Invoice\DTO\InvoiceMapPayload;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

/**
 * Creates a snapshot invoice (DB + PDF) after the customer accepts the commercial offer.
 */
final class InvoiceIssuingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly BillingCompanyRepository $billingCompanyRepository,
        private readonly InvoiceNumberGenerator $invoiceNumberGenerator,
        private readonly InvoiceAddressFormatter $addressFormatter,
        private readonly InvoiceStaticMapFetcher $staticMapFetcher,
        private readonly ChromiumInvoicePdfRenderer $pdfRenderer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly StoredPdfPathResolver $storedPdfPathResolver,
        #[Autowire('%env(int:PLATFORM_FEE_PERCENT)%')]
        private readonly int $defaultPlatformFeePercent,
        private readonly NotificationDispatchService $notificationDispatchService,
        private readonly DocumentRepository $documentRepository,
        private readonly DocumentNumberFormatter $documentNumberFormatter,
        private readonly InvoicePdfContextBuilder $invoicePdfContextBuilder,
        private readonly SenderOrderPayableTotalCentsCalculator $senderOrderPayableTotalCentsCalculator,
    ) {
    }

    /**
     * Idempotent: same accepted offer never creates a second invoice.
     * Failures are logged; order confirmation must not break.
     */
    public function issueForAcceptedOrder(Order $order): void
    {
        try {
            $this->doIssue($order);
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf(
                    'Invoice issuing failed (order_id=%s): %s',
                    $order->getId()?->toRfc4122() ?? '?',
                    $e->getMessage()
                ),
                [
                    'order_id' => $order->getId()?->toRfc4122(),
                    'exception' => $e,
                ]
            );
        }
    }

    private function doIssue(Order $order): void
    {
        $offer = $order->getLatestOffer();
        if (!$offer instanceof OrderOffer || $offer->getStatus() !== OrderOffer::STATUS['ACCEPTED']) {
            return;
        }

        if ($this->invoiceRepository->findOneByOrderOffer($offer) !== null) {
            return;
        }

        if ($this->billingCompanyRepository->countIssuingCompanies() !== 1) {
            throw new \RuntimeException('Exactly one BillingCompany must have "Issues invoices".');
        }

        $issuer = $this->billingCompanyRepository->findIssuingCompany();
        if (!$issuer instanceof BillingCompany) {
            throw new \RuntimeException('Issuing BillingCompany not found.');
        }

        $sender = $order->getSender();
        if (!$sender instanceof User) {
            throw new \RuntimeException('Order has no sender.');
        }

        $this->assertIssuerComplete($issuer);
        $this->assertBuyerBillingComplete($sender);

        $tz = new \DateTimeZone('Europe/Riga');
        $issueDate = new \DateTimeImmutable('today', $tz);
        $dueDate = $this->resolveDueDate($issuer, $issueDate);

        $currency = $order->getCurrency() ?? 'EUR';
        $feeAmount = $offer->getFee() ?? 0;
        $subtotal = $offer->getNetto() ?? 0;
        $freight = $subtotal - $feeAmount;
        if ($freight < 0) {
            $freight = 0;
        }

        $splitPayable = $this->senderOrderPayableTotalCentsCalculator->computePayableGrossAndVatCents($offer);
        if ($splitPayable !== null) {
            $vatAmount = $splitPayable['vat_cents'];
            $gross = $splitPayable['gross_cents'];
        } else {
            $vatAmount = $offer->getVat() ?? 0;
            $gross = $offer->getBrutto() ?? 0;
        }

        $vatRateStr = (string) $issuer->getVatRate();
        $feePercentFloat = $this->resolveFeePercentFloat($issuer);
        $feePercentStr = $this->normalizeDecimalString($feePercentFloat);

        [$sellerLine1, $sellerLine2] = $this->addressFormatter->formatSellerLegalAddress($issuer);
        $buyerAddress = $this->addressFormatter->formatBuyerAddress($sender->getCompanyAddress());

        $buyerEmail = trim((string) $sender->getEmail());
        $buyerEmail = $buyerEmail !== '' ? $buyerEmail : null;

        $invoice = new Invoice();
        $invoice->setStatus(Invoice::STATUS_PDF_FAILED);
        $invoice->setIssueDate($issueDate);
        $invoice->setDueDate($dueDate);
        $invoice->setCurrency($currency);
        $invoice->setAmountFreight($freight);
        $invoice->setAmountCommission($feeAmount);
        $invoice->setAmountSubtotal($subtotal);
        $invoice->setAmountVat($vatAmount);
        $invoice->setAmountGross($gross);
        $invoice->setVatRatePercent($vatRateStr);
        $invoice->setFeePercent($feePercentStr);
        $invoice->setSellerName($issuer->getName() ?? '');
        $invoice->setSellerRegistrationNumber($issuer->getRegistrationNumber() ?? '');
        $invoice->setSellerVatNumber($issuer->getVatNumber());
        $invoice->setSellerAddressLine1($sellerLine1);
        $invoice->setSellerAddressLine2($sellerLine2);
        $invoice->setSellerEmail($issuer->getEmail() ?? '');
        $invoice->setSellerPhone($issuer->getPhone() ?? '');
        $invoice->setBuyerCompanyName($sender->getCompanyName() ?? '');
        $invoice->setBuyerRegistrationNumber($sender->getCompanyRegistrationNumber() ?? '');
        $invoice->setBuyerVatNumber($sender->getVatNumber());
        $invoice->setBuyerAddress($buyerAddress);
        $invoice->setBuyerEmailSnapshot($buyerEmail);
        $invoice->setOrderReference($order->getReference());
        $invoice->setPickupAddress($order->getPickupAddress() ?? '');
        $invoice->setDeliveryAddress($order->getDropoutAddress() ?? '');
        $invoice->setOrderOffer($offer);
        $invoice->setRelatedOrder($order);

        $this->em->wrapInTransaction(function () use ($invoice, $issueDate): void {
            $number = $this->invoiceNumberGenerator->allocateNextSequence($issueDate);
            $invoice->setInvoiceNumber($number);
            $this->em->persist($invoice);
            $this->em->flush();
        });

        $mapPayload = $this->staticMapFetcher->fetchMapPayload(
            $order->getPickupLatitude(),
            $order->getPickupLongitude(),
            $order->getDropoutLatitude(),
            $order->getDropoutLongitude()
        );

        $ctx = $this->invoicePdfContextBuilder->build(
            $invoice,
            $mapPayload,
            $sender,
            $issuer,
            'PAYMENT_NOTICE',
            $this->documentNumberFormatter->formatPaymentNoticeNumber((string) $invoice->getInvoiceNumber()),
            null,
        );

        try {
            $html = $this->twig->render('invoice/pdf.html.twig', $ctx);
            $pdfBinary = $this->pdfRenderer->renderHtmlToPdf($html);
        } catch (\Throwable $e) {
            $invoice->setPdfError($e->getMessage());
            $invoice->setStatus(Invoice::STATUS_PDF_FAILED);
            $this->em->flush();

            return;
        }

        $id = $invoice->getId();
        if (!$id instanceof Uuid) {
            throw new \RuntimeException('Invoice has no id after persist.');
        }

        $orderUuid = $order->getId()?->toRfc4122();
        if ($orderUuid === null || $orderUuid === '') {
            throw new \RuntimeException('Order has no id for PDF storage path.');
        }

        $relativePath = sprintf('%s/%s.pdf', $orderUuid, $id->toRfc4122());
        $fullPath = $this->storedPdfPathResolver->getPrimaryDir() . '/' . $relativePath;
        $dir = dirname($fullPath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Cannot create directory "%s".', $dir));
        }

        if (file_put_contents($fullPath, $pdfBinary) === false) {
            $invoice->setPdfError('Failed to write PDF file.');
            $invoice->setStatus(Invoice::STATUS_PDF_FAILED);
            $this->em->flush();

            return;
        }

        $invoice->setPdfRelativePath($relativePath);
        $invoice->setPdfError(null);

        $this->persistPaymentNoticeDocument($invoice, $issuer, $sender);
        $this->em->flush();

        if ($buyerEmail === null) {
            $invoice->setStatus(Invoice::STATUS_EMAIL_NOT_SENT);
            $this->em->flush();

            return;
        }

        $relatedOrder = $invoice->getRelatedOrder();
        if ($relatedOrder === null) {
            $invoice->setStatus(Invoice::STATUS_EMAIL_FAILED);
            $invoice->setEmailError('Invoice has no related order for notification dispatch.');
            $this->em->flush();

            return;
        }

        $dispatch = $this->notificationDispatchService->dispatch(
            $relatedOrder,
            NotificationEventKey::ORDER_PRICE_CONFIRMED,
            $invoice,
            false,
            null,
        );

        if ($dispatch->getSentCount() > 0) {
            $invoice->setStatus(Invoice::STATUS_EMAIL_SENT);
            $invoice->setEmailError(null);
            if ($relatedOrder->getStatus() === Order::STATUS['ACCEPTED']) {
                $relatedOrder->setStatus(Order::STATUS['INVOICED']);
            }
        } elseif ($dispatch->getFailedCount() > 0) {
            $invoice->setStatus(Invoice::STATUS_EMAIL_FAILED);
            $invoice->setEmailError($dispatch->getFirstError() ?? 'Notification dispatch failed.');
        } else {
            $invoice->setStatus(Invoice::STATUS_EMAIL_NOT_SENT);
            $invoice->setEmailError(
                $dispatch->getMatchedRuleCount() === 0
                    ? 'No active notification rules for ORDER_PRICE_CONFIRMED.'
                    : null
            );
        }

        $this->em->flush();
    }

    private function normalizeDecimalString(float $f): string
    {
        return rtrim(rtrim(sprintf('%.4F', $f), '0'), '.');
    }

    private function resolveFeePercentFloat(BillingCompany $issuer): float
    {
        $p = $issuer->getPlatformFeePercent();
        if ($p !== null && $p !== '') {
            return (float) $p;
        }

        return (float) $this->defaultPlatformFeePercent;
    }

    private function resolveDueDate(BillingCompany $issuer, \DateTimeImmutable $issueDate): \DateTimeImmutable
    {
        $days = $issuer->getPaymentDueDays();
        if ($days === null || $days === 0) {
            return $issueDate;
        }

        return $issueDate->modify('+' . $days . ' days');
    }

    private function assertIssuerComplete(BillingCompany $c): void
    {
        $required = [
            $c->getName(),
            $c->getRegistrationNumber(),
            $c->getAddressStreet(),
            $c->getAddressNumber(),
            $c->getAddressCity(),
            $c->getAddressCountry(),
            $c->getAddressPostalCode(),
            $c->getIban(),
            $c->getPhone(),
            $c->getEmail(),
            $c->getVatRate(),
        ];
        foreach ($required as $v) {
            if ($v === null || trim((string) $v) === '') {
                throw new \RuntimeException('Issuing company legal data is incomplete.');
            }
        }
    }

    private function assertBuyerBillingComplete(User $u): void
    {
        foreach ([$u->getCompanyName(), $u->getCompanyRegistrationNumber(), $u->getCompanyAddress()] as $v) {
            if ($v === null || trim((string) $v) === '') {
                throw new \RuntimeException('Buyer company billing data is incomplete.');
            }
        }
    }

    /**
     * Registers the payment notice row; {@see Document::filePath} matches the invoice PDF relative path
     * under {@see StoredPdfPathResolver} primary storage ({orderUuid}/{invoiceUuid}.pdf).
     */
    private function persistPaymentNoticeDocument(
        Invoice $invoice,
        BillingCompany $issuer,
        User $sender,
    ): void {
        $order = $invoice->getRelatedOrder();
        if ($order === null) {
            return;
        }

        if ($this->documentRepository->findOneByOrderAndType($order, DocumentType::PAYMENT_NOTICE) !== null) {
            return;
        }

        $invoiceNumber = $invoice->getInvoiceNumber();
        if ($invoiceNumber === null || $invoiceNumber === '') {
            return;
        }

        $pdfPath = $invoice->getPdfRelativePath();
        if ($pdfPath === null || $pdfPath === '') {
            return;
        }

        $document = new Document();
        $document->setRelatedOrder($order);
        $document->setDocumentType(DocumentType::PAYMENT_NOTICE);
        $document->setDocumentNumber($this->documentNumberFormatter->formatPaymentNoticeNumber($invoiceNumber));
        $document->setSenderCompany($sender);
        $document->setReceiverCompany($issuer);
        $document->setCarrierCompany($order->getCarrier());
        $document->setFilePath($pdfPath);
        $document->setAmountNet((int) $invoice->getAmountSubtotal());
        $document->setAmountVat((int) $invoice->getAmountVat());
        $document->setAmountTotal((int) $invoice->getAmountGross());
        $document->setStatus(DocumentStatus::GENERATED);

        $this->em->persist($document);
    }
}

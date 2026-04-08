<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Entity\BillingCompany;
use App\Entity\Invoice;
use App\Entity\Order;
use App\Entity\OrderOffer;
use App\Entity\User;
use App\Repository\BillingCompanyRepository;
use App\Repository\InvoiceRepository;
use App\Service\Email\Contract\EmailServiceInterface;
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
        private readonly InvoiceMoneyFormatter $moneyFormatter,
        private readonly InvoiceStaticMapFetcher $staticMapFetcher,
        private readonly ChromiumInvoicePdfRenderer $pdfRenderer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%/var/invoices')]
        private readonly string $invoiceStorageDir,
        #[Autowire('%env(int:PLATFORM_FEE_PERCENT)%')]
        private readonly int $defaultPlatformFeePercent,
        private readonly EmailServiceInterface $emailService,
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

        $vatAmount = $offer->getVat() ?? 0;
        $gross = $offer->getBrutto() ?? 0;

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

        $ctx = $this->buildTwigContext($invoice, $mapPayload);

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

        $relativePath = sprintf(
            '%s/%s/%s.pdf',
            $issueDate->format('Y'),
            $issueDate->format('m'),
            $id->toRfc4122()
        );
        $fullPath = $this->invoiceStorageDir . '/' . $relativePath;
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

        if ($buyerEmail === null) {
            $invoice->setStatus(Invoice::STATUS_EMAIL_NOT_SENT);
            $this->em->flush();

            return;
        }

        $subject = 'Rēķins ' . $invoice->getInvoiceNumber();
        $bodyHtml = sprintf(
            '<p>Sveiki,</p><p>Pielikumā nosūtām rēķinu <strong>%s</strong>.</p><p>Ar cieņu,<br>%s</p>',
            htmlspecialchars((string) $invoice->getInvoiceNumber(), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            htmlspecialchars($issuer->getName() ?? 'Hevvi', ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
        $attachmentName = sprintf('rekina-%s.pdf', preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) $invoice->getInvoiceNumber()));

        $sent = $this->emailService->sendWithPdfAttachment(
            $buyerEmail,
            $subject,
            $bodyHtml,
            strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml)),
            $attachmentName,
            $pdfBinary
        );

        if ($sent) {
            $invoice->setStatus(Invoice::STATUS_EMAIL_SENT);
            $invoice->setEmailError(null);
        } else {
            $invoice->setStatus(Invoice::STATUS_EMAIL_FAILED);
            $invoice->setEmailError('Mailjet send returned failure.');
        }

        $this->em->flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTwigContext(Invoice $invoice, InvoiceMapPayload $map): array
    {
        $c = $invoice->getCurrency() ?? 'EUR';

        return [
            'invoice_number' => $invoice->getInvoiceNumber(),
            'issue_date' => $invoice->getIssueDate()?->format('d.m.Y'),
            'due_date' => $invoice->getDueDate()?->format('d.m.Y'),
            'service_date' => $invoice->getIssueDate()?->format('d.m.Y'),
            'seller_name' => $invoice->getSellerName(),
            'seller_reg' => $invoice->getSellerRegistrationNumber(),
            'seller_vat' => $invoice->getSellerVatNumber() ?? '—',
            'seller_line1' => $invoice->getSellerAddressLine1(),
            'seller_line2' => $invoice->getSellerAddressLine2(),
            'seller_email' => $invoice->getSellerEmail(),
            'seller_phone' => $invoice->getSellerPhone(),
            'buyer_company' => $invoice->getBuyerCompanyName(),
            'buyer_reg' => $invoice->getBuyerRegistrationNumber(),
            'buyer_vat' => $invoice->getBuyerVatNumber() ?? '—',
            'buyer_address_lines' => array_values(array_filter(explode("\n", $invoice->getBuyerAddress() ?? ''), static fn (string $l): bool => trim($l) !== '')),
            'order_reference' => $invoice->getOrderReference(),
            'pickup' => $invoice->getPickupAddress(),
            'delivery' => $invoice->getDeliveryAddress(),
            'map_data_uri' => $map->imageDataUri,
            'map_show_pins' => $map->showPins,
            'map_pickup_left' => $map->pickupLeftPx,
            'map_pickup_top' => $map->pickupTopPx,
            'map_drop_left' => $map->dropLeftPx,
            'map_drop_top' => $map->dropTopPx,
            'amount_freight' => $this->moneyFormatter->formatCents((int) $invoice->getAmountFreight(), $c),
            'amount_commission' => $this->moneyFormatter->formatCents((int) $invoice->getAmountCommission(), $c),
            'amount_subtotal' => $this->moneyFormatter->formatCents((int) $invoice->getAmountSubtotal(), $c),
            'amount_vat' => $this->moneyFormatter->formatCents((int) $invoice->getAmountVat(), $c),
            'amount_gross' => $this->moneyFormatter->formatCents((int) $invoice->getAmountGross(), $c),
            'vat_label' => 'PVN ' . $this->formatPercentLabel((string) $invoice->getVatRatePercent()) . '%',
            'commission_label' => 'Hevvi.app komisija (' . $this->formatPercentLabel((string) $invoice->getFeePercent()) . '%, bez PVN)',
        ];
    }

    private function formatPercentLabel(string $decimal): string
    {
        $f = (float) $decimal;
        if (abs($f - round($f)) < 0.0001) {
            return (string) (int) round($f);
        }

        return rtrim(rtrim(number_format($f, 2, ',', ''), '0'), ',');
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
}

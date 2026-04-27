<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Invoice;
use App\Entity\Order;
use App\Entity\User;
use App\Enum\DocumentType;
use App\Repository\DocumentRepository;
use App\Repository\OrderHistoryRepository;
use App\Service\Document\DocumentNumberFormatter;
use App\Service\Invoice\InvoiceMoneyFormatter;

/**
 * Builds placeholder map for notification templates (see docs/EMAIL_NOTIFICATIONS.md).
 */
final class NotificationContextFactory
{
    public function __construct(
        private readonly InvoiceMoneyFormatter $moneyFormatter,
        private readonly DocumentRepository $documentRepository,
        private readonly DocumentNumberFormatter $documentNumberFormatter,
        private readonly OrderHistoryRepository $orderHistoryRepository,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function build(Order $order, ?Invoice $invoice = null): array
    {
        $empty = $this->emptyPlaceholders();
        $filled = $this->fillFromOrder($order);
        $merged = array_merge($empty, $filled);
        $merged = array_merge($merged, $this->fillPaymentNoticePlaceholders($order, $invoice));

        if ($invoice instanceof Invoice) {
            $merged = array_merge($merged, $this->fillFromInvoice($invoice));
        }

        // Aliases: city placeholders = full address (product decision)
        $merged['PICKUP_CITY'] = $merged['PICKUP_ADDRESS'];
        $merged['DELIVERY_CITY'] = $merged['DELIVERY_ADDRESS'];

        return $merged;
    }

    /**
     * @return array<string, string>
     */
    private function emptyPlaceholders(): array
    {
        $keys = [
            'ORDER_ID', 'ORDER_INTERNAL_ID', 'ORDER_STATUS',
            'CLIENT_NAME', 'CLIENT_PHONE', 'CLIENT_EMAIL',
            'CARRIER_NAME', 'CARRIER_PHONE',
            'PICKUP_ADDRESS', 'DELIVERY_ADDRESS', 'PICKUP_CITY', 'DELIVERY_CITY',
            'PICKUP_DATE', 'PICKUP_TIME', 'DELIVERY_DATE', 'DELIVERY_TIME', 'ETA',
            'CARGO_DESCRIPTION', 'RECEIVER_NAME', 'PICKUP_CONTACT',
            'INVOICE_NUMBER', 'INVOICE_DATE', 'TOTAL_AMOUNT', 'CURRENCY', 'PAYMENT_DUE_DATE',
            'PAYMENT_NOTICE_NUMBER', 'PAYMENT_NOTICE_DATE',
        ];

        return array_fill_keys($keys, '');
    }

    /**
     * @return array<string, string>
     */
    private function fillFromOrder(Order $order): array
    {
        $sender = $order->getSender();
        $carrier = $order->getCarrier();

        $statusLabel = '';
        $st = $order->getStatus();
        if ($st !== null) {
            $flip = array_flip(Order::STATUS);
            $statusLabel = $flip[$st] ?? (string) $st;
        }

        $clientName = '';
        $clientPhone = '';
        $clientEmail = '';
        if ($sender instanceof User) {
            $fn = trim((string) ($sender->getFirstName() ?? ''));
            $ln = trim((string) ($sender->getLastName() ?? ''));
            $person = trim($fn.' '.$ln);
            $clientName = trim((string) ($sender->getCompanyName() ?: $person));
            $clientPhone = trim((string) ($sender->getPhone() ?? ''));
            $clientEmail = trim((string) ($sender->getEmail() ?? ''));
        }

        $carrierName = '';
        $carrierPhone = '';
        if ($carrier !== null) {
            $carrierName = trim($carrier->getLegalName() ?? '');
            $carrierPhone = trim((string) ($carrier->getPhone() ?? ''));
        }

        $pickupContact = $clientName !== '' ? $clientName : $clientPhone;
        if ($clientPhone !== '' && $clientName !== '') {
            $pickupContact = $clientName.' / '.$clientPhone;
        }

        $deliveredHistory = $this->orderHistoryRepository->findLatestForOrderAndStatus(
            $order,
            Order::STATUS['DELIVERED'],
        );
        $deliveredAt = $deliveredHistory?->getCreatedAt();

        $deliveryDate = $deliveredAt !== null
            ? $this->formatDate($deliveredAt)
            : $this->formatDate($order->getDeliveryDate());
        $deliveryTime = $deliveredAt !== null
            ? $deliveredAt->format('H:i')
            : $this->formatTimeWindow($order->getDeliveryTimeFrom(), $order->getDeliveryTimeTo());

        return [
            'ORDER_ID' => $order->getReference(),
            'ORDER_INTERNAL_ID' => $order->getId()?->toRfc4122() ?? '',
            'ORDER_STATUS' => $statusLabel,
            'CLIENT_NAME' => $clientName,
            'CLIENT_PHONE' => $clientPhone,
            'CLIENT_EMAIL' => $clientEmail,
            'CARRIER_NAME' => $carrierName,
            'CARRIER_PHONE' => $carrierPhone,
            'PICKUP_ADDRESS' => trim((string) ($order->getPickupAddress() ?? '')),
            'DELIVERY_ADDRESS' => trim((string) ($order->getDropoutAddress() ?? '')),
            'PICKUP_DATE' => $this->formatDate($order->getPickupDate()),
            'PICKUP_TIME' => $this->formatTimeWindow($order->getPickupTimeFrom(), $order->getPickupTimeTo()),
            'DELIVERY_DATE' => $deliveryDate,
            'DELIVERY_TIME' => $deliveryTime,
            'ETA' => $this->buildEta($order),
            'CARGO_DESCRIPTION' => $this->buildCargoDescription($order),
            'RECEIVER_NAME' => $clientName,
            'PICKUP_CONTACT' => $pickupContact,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fillFromInvoice(Invoice $invoice): array
    {
        $c = $invoice->getCurrency() ?? 'EUR';
        $gross = (int) $invoice->getAmountGross();

        return [
            'INVOICE_NUMBER' => (string) ($invoice->getInvoiceNumber() ?? ''),
            'INVOICE_DATE' => $invoice->getIssueDate()?->format('d.m.Y') ?? '',
            'TOTAL_AMOUNT' => $this->moneyFormatter->formatCentsNumber($gross),
            'CURRENCY' => $c,
            'PAYMENT_DUE_DATE' => $invoice->getDueDate()?->format('d.m.Y') ?? '',
        ];
    }

    /**
     * Payment notice row (documents) or derived number (invoice no. + "-PN") for ORDER_PRICE_CONFIRMED emails.
     *
     * @return array{PAYMENT_NOTICE_NUMBER: string, PAYMENT_NOTICE_DATE: string}
     */
    private function fillPaymentNoticePlaceholders(Order $order, ?Invoice $invoice): array
    {
        $doc = $this->documentRepository->findOneByOrderAndType($order, DocumentType::PAYMENT_NOTICE);
        if ($doc !== null) {
            return [
                'PAYMENT_NOTICE_NUMBER' => $doc->getDocumentNumber(),
                'PAYMENT_NOTICE_DATE' => $doc->getIssuedAt()?->format('d.m.Y') ?? '',
            ];
        }

        $invNo = $invoice?->getInvoiceNumber();
        if ($invNo !== null && $invNo !== '') {
            return [
                'PAYMENT_NOTICE_NUMBER' => $this->documentNumberFormatter->formatPaymentNoticeNumber((string) $invNo),
                'PAYMENT_NOTICE_DATE' => $invoice->getIssueDate()?->format('d.m.Y') ?? '',
            ];
        }

        return [
            'PAYMENT_NOTICE_NUMBER' => '',
            'PAYMENT_NOTICE_DATE' => '',
        ];
    }

    private function formatDate(?\DateTimeInterface $d): string
    {
        if ($d === null) {
            return '';
        }

        return $d->format('d.m.Y');
    }

    private function formatTimeWindow(?\DateTimeInterface $from, ?\DateTimeInterface $to): string
    {
        if ($from === null && $to === null) {
            return '';
        }
        $a = $from?->format('H:i') ?? '';
        $b = $to?->format('H:i') ?? '';
        if ($a !== '' && $b !== '') {
            return $a.'–'.$b;
        }

        return $a !== '' ? $a : $b;
    }

    /**
     * Planned delivery deadline: 48 hours from first transition to PAID (payment).
     * Format: d.m.Y and 24-hour clock (H:i). Falls back to order delivery date/window if PAID history is missing.
     */
    private function buildEta(Order $order): string
    {
        $paidHistory = $this->orderHistoryRepository->findEarliestForOrderAndStatus(
            $order,
            Order::STATUS['PAID'],
        );
        $paidAt = $paidHistory?->getCreatedAt();
        if ($paidAt !== null) {
            return $paidAt->modify('+48 hours')->format('d.m.Y H:i');
        }

        $date = $this->formatDate($order->getDeliveryDate());
        $tw = $this->formatTimeWindow($order->getDeliveryTimeFrom(), $order->getDeliveryTimeTo());
        if ($date === '') {
            return $tw;
        }
        if ($tw === '') {
            return $date;
        }

        return $date.' '.$tw;
    }

    private function buildCargoDescription(Order $order): string
    {
        $parts = [];
        foreach ($order->getCargo() as $cargo) {
            $qty = $cargo->getQuantity() ?? 0;
            $kg = $cargo->getWeightKg() ?? 0;
            $line = trim(($cargo->getName() ?? '').' ×'.$qty.' · '.$kg.' kg');
            if ($line !== '') {
                $parts[] = $line;
            }
        }

        return implode('; ', $parts);
    }
}

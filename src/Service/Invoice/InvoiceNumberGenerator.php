<?php

declare(strict_types=1);

namespace App\Service\Invoice;

/**
 * Invoice numbers for new issues: HEV + zero-padded order number (e.g. HEV00034).
 * Document numbers append -PN / -SN / -CR via {@see \App\Service\Document\DocumentNumberFormatter}.
 *
 * Legacy invoices keep their historical daily sequence (HEV + ddmmyy + NN); this
 * generator is only used when issuing new invoices.
 */
final class InvoiceNumberGenerator
{
    private const PREFIX = 'HEV';

    private const ORDER_NUMBER_PAD = 5;

    /**
     * Build invoice number from the order's human number.
     *
     * @throws \InvalidArgumentException when order number is missing or invalid
     */
    public function fromOrderNumber(int $orderNumber): string
    {
        if ($orderNumber < 1) {
            throw new \InvalidArgumentException('Order number must be a positive integer.');
        }

        return self::PREFIX . str_pad((string) $orderNumber, self::ORDER_NUMBER_PAD, '0', STR_PAD_LEFT);
    }
}

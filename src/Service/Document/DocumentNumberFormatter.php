<?php

declare(strict_types=1);

namespace App\Service\Document;

/**
 * Human-visible document numbers derived from the invoice number base.
 *
 * New invoices use HEV + order number (e.g. HEV00034); documents append a suffix:
 * HEV00034-PN / HEV00034-SN / HEV00034-CR. Legacy invoice bases (HEV + ddmmyy + NN)
 * keep working the same way.
 */
final class DocumentNumberFormatter
{
    /**
     * Payment notice: invoice base + -PN (e.g. HEV00034-PN).
     */
    public function formatPaymentNoticeNumber(string $invoiceNumber): string
    {
        return $invoiceNumber . '-PN';
    }

    /**
     * Customer (sender) invoice after delivery (e.g. HEV00034-SN).
     */
    public function formatCustomerInvoiceNumber(string $invoiceNumber): string
    {
        return $invoiceNumber . '-SN';
    }

    /**
     * Carrier-facing invoice number (e.g. HEV00034-CR).
     */
    public function formatCarrierInvoiceNumber(string $invoiceNumber): string
    {
        return $invoiceNumber . '-CR';
    }
}

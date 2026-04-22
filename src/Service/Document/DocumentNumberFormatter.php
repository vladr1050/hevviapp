<?php

declare(strict_types=1);

namespace App\Service\Document;

/**
 * Human-visible document numbers derived from the financial sequence (invoice table).
 */
final class DocumentNumberFormatter
{
    /**
     * Payment notice uses the same sequence base as the invoice row, with a -PN suffix
     * (e.g. HEV01042501 → HEV01042501-PN), same pattern as carrier -CR.
     */
    public function formatPaymentNoticeNumber(string $invoiceNumber): string
    {
        return $invoiceNumber . '-PN';
    }

    /**
     * Customer (sender) invoice after delivery (e.g. HEV01042501-SN).
     */
    public function formatCustomerInvoiceNumber(string $invoiceNumber): string
    {
        return $invoiceNumber . '-SN';
    }

    /**
     * Carrier-facing invoice number (e.g. HEV01042501-CR).
     */
    public function formatCarrierInvoiceNumber(string $invoiceNumber): string
    {
        return $invoiceNumber . '-CR';
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Invoice;

/**
 * Latvian-style money display for invoice PDF (no Twig math).
 */
final class InvoiceMoneyFormatter
{
    public function formatCents(int $amountCents, string $currency): string
    {
        $value = $amountCents / 100;

        return number_format($value, 2, ',', ' ') . ' ' . $currency;
    }
}

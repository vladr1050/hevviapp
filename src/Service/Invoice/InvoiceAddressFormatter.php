<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Entity\BillingCompany;

final class InvoiceAddressFormatter
{
    /**
     * @return array{0: string, 1: ?string} Two lines for seller block
     */
    public function formatSellerLegalAddress(BillingCompany $c): array
    {
        $line1 = trim($c->getAddressStreet() . ' ' . $c->getAddressNumber());
        $line2 = trim($c->getAddressPostalCode() . ' ' . $c->getAddressCity() . ', ' . $c->getAddressCountry());

        return [$line1, $line2 !== '' ? $line2 : null];
    }

    /**
     * Multiline buyer address for PDF (plain newlines).
     */
    public function formatBuyerAddress(?string $companyAddress): string
    {
        $raw = trim((string) $companyAddress);
        if ($raw === '') {
            return '';
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);

        return $normalized;
    }
}

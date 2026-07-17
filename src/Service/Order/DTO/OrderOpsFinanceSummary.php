<?php

declare(strict_types=1);

namespace App\Service\Order\DTO;

/**
 * Financial aggregates for the ops dashboard period (invoice-based).
 */
final class OrderOpsFinanceSummary
{
    public function __construct(
        public readonly int $invoiceCount,
        public readonly int $freightNetCents,
        public readonly int $commissionNetCents,
        public readonly int $vatCents,
        public readonly int $grossCents,
        public readonly string $currency = 'EUR',
    ) {
    }
}

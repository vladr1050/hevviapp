<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use Doctrine\DBAL\Connection;

/**
 * Atomic daily sequence for invoice numbers (HEV + ddmmyy + NN).
 */
final class InvoiceNumberGenerator
{
    private const PREFIX = 'HEV';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Must run inside the same DB transaction as the invoice INSERT.
     */
    public function allocateNextSequence(\DateTimeImmutable $issueDate): string
    {
        $day = $issueDate->format('Y-m-d');
        $ddmmyy = $issueDate->format('dmy');

        $seq = (int) $this->connection->fetchOne(
            <<<'SQL'
INSERT INTO invoice_day_counter (day, last_seq) VALUES (?, 1)
ON CONFLICT (day) DO UPDATE SET last_seq = invoice_day_counter.last_seq + 1
RETURNING last_seq
SQL
            ,
            [$day]
        );

        $nn = str_pad((string) $seq, 2, '0', STR_PAD_LEFT);

        return self::PREFIX . $ddmmyy . $nn;
    }
}

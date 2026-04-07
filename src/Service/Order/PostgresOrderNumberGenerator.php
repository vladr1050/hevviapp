<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains
 * the property of SIA SLYFOX, its suppliers and Customers,
 * if any.  The intellectual and technical concepts contained
 * herein are proprietary to SIA SLYFOX
 * its Suppliers and Customers are protected by trade secret or copyright law.
 *
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained.
 */

namespace App\Service\Order;

use App\Service\Order\Contract\OrderNumberGeneratorInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;

/**
 * Генерирует номер заказа через PostgreSQL SEQUENCE.
 *
 * nextval() атомарен по определению: каждый вызов гарантированно
 * возвращает уникальное значение даже при параллельных транзакциях.
 * "Дырки" в последовательности (при откате транзакции) — норма.
 *
 * Sequence не в схеме Doctrine ORM — её может не быть после рассинхрона или schema:update.
 * Тогда один раз восстанавливаем (как в Version20260401160000).
 */
final class PostgresOrderNumberGenerator implements OrderNumberGeneratorInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function generate(): int
    {
        try {
            return (int) $this->connection->fetchOne("SELECT nextval('order_number_seq')");
        } catch (TableNotFoundException $e) {
            if (!str_contains($e->getMessage(), 'order_number_seq')) {
                throw $e;
            }
            $this->ensureOrderNumberSequence();

            return (int) $this->connection->fetchOne("SELECT nextval('order_number_seq')");
        }
    }

    private function ensureOrderNumberSequence(): void
    {
        $this->connection->executeStatement(
            'CREATE SEQUENCE IF NOT EXISTS order_number_seq START 1 INCREMENT 1 CACHE 1'
        );

        $this->connection->fetchOne(<<<'SQL'
SELECT setval(
    'order_number_seq',
    COALESCE((SELECT MAX(order_number) FROM "order"), 0) + 1,
    false
)
SQL
        );
    }
}

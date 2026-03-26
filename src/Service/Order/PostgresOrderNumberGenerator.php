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

/**
 * Генерирует номер заказа через PostgreSQL SEQUENCE.
 *
 * nextval() атомарен по определению: каждый вызов гарантированно
 * возвращает уникальное значение даже при параллельных транзакциях.
 * "Дырки" в последовательности (при откате транзакции) — норма.
 */
final class PostgresOrderNumberGenerator implements OrderNumberGeneratorInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function generate(): int
    {
        return (int) $this->connection->fetchOne("SELECT nextval('order_number_seq')");
    }
}

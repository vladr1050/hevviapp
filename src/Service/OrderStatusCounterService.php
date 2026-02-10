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

namespace App\Service;

use App\Repository\OrderRepository;

/**
 * Сервис для подсчета количества заказов по статусам.
 * Следует принципу Single Responsibility - отвечает только за подсчет статусов.
 */
class OrderStatusCounterService
{
    private ?array $cachedCounts = null;

    public function __construct(
        private readonly OrderRepository $orderRepository
    ) {
    }

    /**
     * Получить количество заказов для каждого статуса.
     *
     * @return array<int, int> Массив где ключ - статус, значение - количество заказов
     */
    public function getStatusCounts(): array
    {
        if ($this->cachedCounts === null) {
            $this->cachedCounts = $this->orderRepository->countByStatus();
        }

        return $this->cachedCounts;
    }

    /**
     * Получить количество заказов для конкретного статуса.
     *
     * @param int $status Код статуса
     * @return int Количество заказов
     */
    public function getCountForStatus(int $status): int
    {
        $counts = $this->getStatusCounts();

        return $counts[$status] ?? 0;
    }

    /**
     * Сбросить кеш (полезно для тестирования или после массовых изменений).
     */
    public function resetCache(): void
    {
        $this->cachedCounts = null;
    }
}

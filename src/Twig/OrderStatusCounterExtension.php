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

namespace App\Twig;

use App\Service\OrderStatusCounterService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig Extension для доступа к счетчикам статусов заказов из шаблонов.
 * Следует принципу Interface Segregation - предоставляет только необходимые функции.
 */
class OrderStatusCounterExtension extends AbstractExtension
{
    public function __construct(
        private readonly OrderStatusCounterService $counterService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('order_status_count', [$this, 'getStatusCount']),
            new TwigFunction('order_status_counts', [$this, 'getStatusCounts']),
        ];
    }

    /**
     * Получить количество заказов для конкретного статуса.
     *
     * @param int $status Код статуса
     * @return int Количество заказов
     */
    public function getStatusCount(int $status): int
    {
        return $this->counterService->getCountForStatus($status);
    }

    /**
     * Получить массив всех счетчиков статусов.
     *
     * @return array<int, int> Массив где ключ - статус, значение - количество заказов
     */
    public function getStatusCounts(): array
    {
        return $this->counterService->getStatusCounts();
    }
}

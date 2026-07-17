<?php

declare(strict_types=1);

namespace App\Service\Order\DTO;

use App\Entity\NotificationLog;
use App\Entity\Order;

/**
 * Snapshot for the ops dashboard (Live/Test + date range).
 */
final class OrderOpsDashboardData
{
    /**
     * @param array<int, int> $statusCounts status => count
     * @param list<Order> $stuckPaid
     * @param list<Order> $stuckOffered
     * @param list<Order> $slaOverdue
     * @param list<NotificationLog> $failedNotifications
     * @param list<Order> $periodNew
     * @param list<Order> $periodPaid
     * @param list<Order> $periodDelivered
     * @param list<NotificationLog> $periodFailedNotifications
     */
    public function __construct(
        public readonly bool $isTest,
        public readonly \DateTimeImmutable $periodFrom,
        public readonly \DateTimeImmutable $periodToExclusive,
        public readonly array $statusCounts,
        public readonly int $orderTotal,
        public readonly int $stuckPaidCount,
        public readonly array $stuckPaid,
        public readonly int $stuckOfferedCount,
        public readonly array $stuckOffered,
        public readonly int $slaOverdueCount,
        public readonly array $slaOverdue,
        public readonly int $failedNotificationsCount,
        public readonly array $failedNotifications,
        public readonly int $periodNewCount,
        public readonly array $periodNew,
        public readonly int $periodPaidCount,
        public readonly array $periodPaid,
        public readonly int $periodDeliveredCount,
        public readonly array $periodDelivered,
        public readonly int $periodFailedNotificationsCount,
        public readonly array $periodFailedNotifications,
        public readonly OrderOpsFinanceSummary $finance,
        public readonly \DateTimeImmutable $generatedAt,
        public readonly string $timezone,
    ) {
    }

    public function periodToInclusive(): \DateTimeImmutable
    {
        return $this->periodToExclusive->modify('-1 day');
    }
}

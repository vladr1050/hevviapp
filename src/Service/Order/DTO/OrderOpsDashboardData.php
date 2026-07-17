<?php

declare(strict_types=1);

namespace App\Service\Order\DTO;

use App\Entity\NotificationLog;
use App\Entity\Order;

/**
 * Snapshot for the Live-only ops dashboard.
 */
final class OrderOpsDashboardData
{
    /**
     * @param array<int, int> $statusCounts status => count
     * @param list<Order> $stuckPaid
     * @param list<Order> $stuckOffered
     * @param list<Order> $slaOverdue
     * @param list<NotificationLog> $failedNotifications
     * @param list<Order> $todayNew
     * @param list<Order> $todayPaid
     * @param list<Order> $todayDelivered
     * @param list<NotificationLog> $todayFailedNotifications
     */
    public function __construct(
        public readonly array $statusCounts,
        public readonly int $liveTotal,
        public readonly int $stuckPaidCount,
        public readonly array $stuckPaid,
        public readonly int $stuckOfferedCount,
        public readonly array $stuckOffered,
        public readonly int $slaOverdueCount,
        public readonly array $slaOverdue,
        public readonly int $failedNotificationsCount,
        public readonly array $failedNotifications,
        public readonly int $todayNewCount,
        public readonly array $todayNew,
        public readonly int $todayPaidCount,
        public readonly array $todayPaid,
        public readonly int $todayDeliveredCount,
        public readonly array $todayDelivered,
        public readonly int $todayFailedNotificationsCount,
        public readonly array $todayFailedNotifications,
        public readonly \DateTimeImmutable $generatedAt,
        public readonly string $timezone,
    ) {
    }
}

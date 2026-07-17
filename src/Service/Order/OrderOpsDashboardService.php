<?php

declare(strict_types=1);

namespace App\Service\Order;

use App\Entity\Order;
use App\Repository\NotificationLogRepository;
use App\Repository\OrderHistoryRepository;
use App\Repository\OrderRepository;
use App\Service\Order\DTO\OrderOpsDashboardData;

/**
 * Builds Live-only ops metrics for the admin dashboard.
 */
final class OrderOpsDashboardService
{
    private const string APP_TZ = 'Europe/Riga';
    private const int STUCK_PAID_HOURS = 24;
    private const int STUCK_OFFERED_HOURS = 48;
    private const int LIST_LIMIT = 25;
    private const int SLA_CANDIDATE_LIMIT = 300;

    /** Statuses where the 48h delivery SLA may still be running. */
    private const array SLA_ACTIVE_STATUSES = [
        Order::STATUS['PAID'],
        Order::STATUS['ASSIGNED'],
        Order::STATUS['AWAITING_PICKUP'],
        Order::STATUS['PICKUP_DONE'],
        Order::STATUS['IN_TRANSIT'],
    ];

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly OrderHistoryRepository $orderHistoryRepository,
        private readonly NotificationLogRepository $notificationLogRepository,
        private readonly DeliveryDeadlineCalculator $deliveryDeadlineCalculator,
    ) {
    }

    public function build(): OrderOpsDashboardData
    {
        $tz = new \DateTimeZone(self::APP_TZ);
        $now = new \DateTimeImmutable('now', $tz);
        $dayStart = $now->setTime(0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        $statusCounts = $this->orderRepository->countLiveByStatus();
        $liveTotal = array_sum($statusCounts);

        $stuckPaidBefore = $now->modify('-' . self::STUCK_PAID_HOURS . ' hours');
        $stuckOfferedBefore = $now->modify('-' . self::STUCK_OFFERED_HOURS . ' hours');

        $slaOverdue = $this->findSlaOverdue($now);
        $failedSince = $now->modify('-7 days');

        return new OrderOpsDashboardData(
            statusCounts: $statusCounts,
            liveTotal: $liveTotal,
            stuckPaidCount: $this->orderRepository->countLiveByStatusOlderThan(
                Order::STATUS['PAID'],
                $stuckPaidBefore,
            ),
            stuckPaid: $this->orderRepository->findLiveByStatusOlderThan(
                Order::STATUS['PAID'],
                $stuckPaidBefore,
                self::LIST_LIMIT,
            ),
            stuckOfferedCount: $this->orderRepository->countLiveByStatusOlderThan(
                Order::STATUS['OFFERED'],
                $stuckOfferedBefore,
            ),
            stuckOffered: $this->orderRepository->findLiveByStatusOlderThan(
                Order::STATUS['OFFERED'],
                $stuckOfferedBefore,
                self::LIST_LIMIT,
            ),
            slaOverdueCount: count($slaOverdue),
            slaOverdue: array_slice($slaOverdue, 0, self::LIST_LIMIT),
            failedNotificationsCount: $this->notificationLogRepository->countFailedForLiveOrdersBetween(
                $failedSince,
                $now->modify('+1 second'),
            ),
            failedNotifications: $this->notificationLogRepository->findFailedForLiveOrdersBetween(
                $failedSince,
                $now->modify('+1 second'),
                self::LIST_LIMIT,
            ),
            todayNewCount: $this->orderRepository->countLiveCreatedBetween($dayStart, $dayEnd),
            todayNew: $this->orderRepository->findLiveCreatedBetween($dayStart, $dayEnd, self::LIST_LIMIT),
            todayPaidCount: $this->orderHistoryRepository->countLiveOrdersWithStatusBetween(
                Order::STATUS['PAID'],
                $dayStart,
                $dayEnd,
            ),
            todayPaid: $this->orderHistoryRepository->findLiveOrdersWithStatusBetween(
                Order::STATUS['PAID'],
                $dayStart,
                $dayEnd,
                self::LIST_LIMIT,
            ),
            todayDeliveredCount: $this->orderHistoryRepository->countLiveOrdersWithStatusBetween(
                Order::STATUS['DELIVERED'],
                $dayStart,
                $dayEnd,
            ),
            todayDelivered: $this->orderHistoryRepository->findLiveOrdersWithStatusBetween(
                Order::STATUS['DELIVERED'],
                $dayStart,
                $dayEnd,
                self::LIST_LIMIT,
            ),
            todayFailedNotificationsCount: $this->notificationLogRepository->countFailedForLiveOrdersBetween(
                $dayStart,
                $dayEnd,
            ),
            todayFailedNotifications: $this->notificationLogRepository->findFailedForLiveOrdersBetween(
                $dayStart,
                $dayEnd,
                self::LIST_LIMIT,
            ),
            generatedAt: $now,
            timezone: self::APP_TZ,
        );
    }

    /**
     * @return list<Order>
     */
    private function findSlaOverdue(\DateTimeImmutable $now): array
    {
        $candidates = $this->orderRepository->findLiveByStatuses(
            self::SLA_ACTIVE_STATUSES,
            self::SLA_CANDIDATE_LIMIT,
        );

        $overdue = [];
        foreach ($candidates as $order) {
            $deadline = $this->deliveryDeadlineCalculator->resolveDeadline($order);
            if ($deadline === null) {
                continue;
            }

            if ($deadline < $now) {
                $overdue[] = $order;
            }
        }

        return $overdue;
    }
}

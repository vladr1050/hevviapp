<?php

declare(strict_types=1);

namespace App\Service\Order;

use App\Entity\Order;
use App\Repository\InvoiceRepository;
use App\Repository\NotificationLogRepository;
use App\Repository\OrderHistoryRepository;
use App\Repository\OrderRepository;
use App\Service\Order\DTO\OrderOpsDashboardData;
use App\Service\Order\DTO\OrderOpsFinanceSummary;

/**
 * Builds ops metrics for Live or Test orders within an optional date range.
 *
 * Date range applies to period activity KPIs and invoice finance.
 * Stuck / SLA / status funnel remain a current snapshot (not date-scoped).
 */
final class OrderOpsDashboardService
{
    private const string APP_TZ = 'Europe/Riga';
    private const int STUCK_PAID_HOURS = 24;
    private const int STUCK_OFFERED_HOURS = 48;
    private const int LIST_LIMIT = 25;
    private const int SLA_CANDIDATE_LIMIT = 300;
    private const int MAX_PERIOD_DAYS = 366;

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
        private readonly InvoiceRepository $invoiceRepository,
        private readonly DeliveryDeadlineCalculator $deliveryDeadlineCalculator,
    ) {
    }

    public function build(
        bool $isTest = false,
        ?\DateTimeImmutable $periodFrom = null,
        ?\DateTimeImmutable $periodToInclusive = null,
    ): OrderOpsDashboardData {
        $tz = new \DateTimeZone(self::APP_TZ);
        $now = new \DateTimeImmutable('now', $tz);

        [$from, $toExclusive] = $this->normalizePeriod($now, $periodFrom, $periodToInclusive);

        $statusCounts = $this->orderRepository->countByStatusAndTestFlag($isTest);
        $orderTotal = array_sum($statusCounts);

        $stuckPaidBefore = $now->modify('-' . self::STUCK_PAID_HOURS . ' hours');
        $stuckOfferedBefore = $now->modify('-' . self::STUCK_OFFERED_HOURS . ' hours');

        $slaOverdue = $this->findSlaOverdue($now, $isTest);
        $failedSince = $now->modify('-7 days');
        $failedUntil = $now->modify('+1 second');

        $financeRow = $this->invoiceRepository->sumAmountsByIssueDateAndTestFlag(
            $from,
            $toExclusive,
            $isTest,
        );

        return new OrderOpsDashboardData(
            isTest: $isTest,
            periodFrom: $from,
            periodToExclusive: $toExclusive,
            statusCounts: $statusCounts,
            orderTotal: $orderTotal,
            stuckPaidCount: $this->orderRepository->countByStatusOlderThanAndTestFlag(
                Order::STATUS['PAID'],
                $stuckPaidBefore,
                $isTest,
            ),
            stuckPaid: $this->orderRepository->findByStatusOlderThanAndTestFlag(
                Order::STATUS['PAID'],
                $stuckPaidBefore,
                $isTest,
                self::LIST_LIMIT,
            ),
            stuckOfferedCount: $this->orderRepository->countByStatusOlderThanAndTestFlag(
                Order::STATUS['OFFERED'],
                $stuckOfferedBefore,
                $isTest,
            ),
            stuckOffered: $this->orderRepository->findByStatusOlderThanAndTestFlag(
                Order::STATUS['OFFERED'],
                $stuckOfferedBefore,
                $isTest,
                self::LIST_LIMIT,
            ),
            slaOverdueCount: count($slaOverdue),
            slaOverdue: array_slice($slaOverdue, 0, self::LIST_LIMIT),
            failedNotificationsCount: $this->notificationLogRepository->countFailedForOrdersBetweenAndTestFlag(
                $failedSince,
                $failedUntil,
                $isTest,
            ),
            failedNotifications: $this->notificationLogRepository->findFailedForOrdersBetweenAndTestFlag(
                $failedSince,
                $failedUntil,
                $isTest,
                self::LIST_LIMIT,
            ),
            periodNewCount: $this->orderRepository->countCreatedBetweenAndTestFlag($from, $toExclusive, $isTest),
            periodNew: $this->orderRepository->findCreatedBetweenAndTestFlag(
                $from,
                $toExclusive,
                $isTest,
                self::LIST_LIMIT,
            ),
            periodPaidCount: $this->orderHistoryRepository->countOrdersWithStatusBetweenAndTestFlag(
                Order::STATUS['PAID'],
                $from,
                $toExclusive,
                $isTest,
            ),
            periodPaid: $this->orderHistoryRepository->findOrdersWithStatusBetweenAndTestFlag(
                Order::STATUS['PAID'],
                $from,
                $toExclusive,
                $isTest,
                self::LIST_LIMIT,
            ),
            periodDeliveredCount: $this->orderHistoryRepository->countOrdersWithStatusBetweenAndTestFlag(
                Order::STATUS['DELIVERED'],
                $from,
                $toExclusive,
                $isTest,
            ),
            periodDelivered: $this->orderHistoryRepository->findOrdersWithStatusBetweenAndTestFlag(
                Order::STATUS['DELIVERED'],
                $from,
                $toExclusive,
                $isTest,
                self::LIST_LIMIT,
            ),
            periodFailedNotificationsCount: $this->notificationLogRepository->countFailedForOrdersBetweenAndTestFlag(
                $from,
                $toExclusive,
                $isTest,
            ),
            periodFailedNotifications: $this->notificationLogRepository->findFailedForOrdersBetweenAndTestFlag(
                $from,
                $toExclusive,
                $isTest,
                self::LIST_LIMIT,
            ),
            finance: new OrderOpsFinanceSummary(
                invoiceCount: $financeRow['invoice_count'],
                freightNetCents: $financeRow['freight_net_cents'],
                commissionNetCents: $financeRow['commission_net_cents'],
                vatCents: $financeRow['vat_cents'],
                grossCents: $financeRow['gross_cents'],
            ),
            generatedAt: $now,
            timezone: self::APP_TZ,
        );
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} [fromInclusive, toExclusive]
     */
    private function normalizePeriod(
        \DateTimeImmutable $now,
        ?\DateTimeImmutable $periodFrom,
        ?\DateTimeImmutable $periodToInclusive,
    ): array {
        $tz = $now->getTimezone();
        $today = $now->setTime(0, 0);

        $from = ($periodFrom ?? $today)->setTimezone($tz)->setTime(0, 0);
        $toInclusive = ($periodToInclusive ?? $today)->setTimezone($tz)->setTime(0, 0);

        if ($toInclusive < $from) {
            [$from, $toInclusive] = [$toInclusive, $from];
        }

        $maxSpan = $from->modify('+' . self::MAX_PERIOD_DAYS . ' days');
        if ($toInclusive > $maxSpan) {
            $toInclusive = $maxSpan;
        }

        return [$from, $toInclusive->modify('+1 day')];
    }

    /**
     * @return list<Order>
     */
    private function findSlaOverdue(\DateTimeImmutable $now, bool $isTest): array
    {
        $candidates = $this->orderRepository->findByStatusesAndTestFlag(
            self::SLA_ACTIVE_STATUSES,
            $isTest,
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

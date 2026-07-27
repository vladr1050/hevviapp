<?php

declare(strict_types=1);

namespace App\Service\Order;

use App\Entity\Order;
use App\Repository\OrderHistoryRepository;

/**
 * Resolves the delivery countdown anchor and deadline for an order.
 *
 * Rules (Europe/Riga, 48h SLA from cargo readiness):
 * - pickupDate is null (ready now) → anchor = max(paidAt, today @ pickupTimeFrom or paidAt);
 * - pickupDate is set (later) → anchor = max(paidAt, pickupDate @ pickupTimeFrom or 09:00),
 *   so a late payment cannot retroactively shrink the SLA window.
 * - deadline = anchor + DELIVERY_SLA_HOURS.
 *
 * Returns null when no PAID history exists yet (timer should not run).
 */
final class DeliveryDeadlineCalculator
{
    public const int DELIVERY_SLA_HOURS = 48;

    private const string APP_TZ = 'Europe/Riga';
    private const string DEFAULT_PICKUP_TIME = '09:00';

    public function __construct(
        private readonly OrderHistoryRepository $orderHistoryRepository,
    ) {
    }

    /**
     * Pickup readiness moment. Null if order has not been paid yet.
     */
    public function resolveAnchor(Order $order): ?\DateTimeImmutable
    {
        $paidAt = $this->resolvePaidAt($order);
        if ($paidAt === null) {
            return null;
        }

        $tz = new \DateTimeZone(self::APP_TZ);
        $paidAtRiga = $paidAt->setTimezone($tz);
        $pickupAnchor = $this->buildPickupAnchor($order, $paidAtRiga);

        if ($pickupAnchor === null) {
            return $paidAtRiga;
        }

        return $paidAtRiga > $pickupAnchor ? $paidAtRiga : $pickupAnchor;
    }

    /**
     * 48h deadline from anchor. Null if anchor cannot be resolved (no PAID yet).
     */
    public function resolveDeadline(Order $order): ?\DateTimeImmutable
    {
        $anchor = $this->resolveAnchor($order);
        if ($anchor === null) {
            return null;
        }

        return $anchor->modify('+' . self::DELIVERY_SLA_HOURS . ' hours');
    }

    /**
     * First moment the order entered PAID status.
     */
    private function resolvePaidAt(Order $order): ?\DateTimeImmutable
    {
        $paid = $this->orderHistoryRepository->findEarliestForOrderAndStatus(
            $order,
            Order::STATUS['PAID'],
        );

        return $paid?->getCreatedAt();
    }

    /**
     * Build pickup readiness moment in Europe/Riga.
     * Uses pickupDate when set; otherwise uses the paid day (ready-now window).
     */
    private function buildPickupAnchor(
        Order $order,
        \DateTimeImmutable $paidAtRiga,
    ): ?\DateTimeImmutable {
        $tz = new \DateTimeZone(self::APP_TZ);
        $date = $order->getPickupDate();
        $day = $date !== null
            ? $date->format('Y-m-d')
            : $paidAtRiga->format('Y-m-d');

        $time = $order->getPickupTimeFrom()?->format('H:i') ?? self::DEFAULT_PICKUP_TIME;

        $anchor = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i',
            $day . ' ' . $time,
            $tz,
        );

        if ($anchor === false) {
            $anchor = new \DateTimeImmutable($day . ' ' . self::DEFAULT_PICKUP_TIME, $tz);
        }

        // Ready-now without an explicit From: stay on paidAt (caller already returns it).
        if ($date === null && $order->getPickupTimeFrom() === null) {
            return null;
        }

        return $anchor;
    }
}

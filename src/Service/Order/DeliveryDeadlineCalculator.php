<?php

declare(strict_types=1);

namespace App\Service\Order;

use App\Entity\Order;
use App\Repository\OrderHistoryRepository;

/**
 * Resolves the delivery countdown anchor and deadline for an order.
 *
 * Rules (Europe/Riga, 48h SLA from cargo readiness):
 * - pickupDate is null → anchor = first PAID history createdAt;
 * - pickupDate is set  → anchor = pickupDate at pickupTimeFrom (fallback 09:00) Europe/Riga
 *   (strict; payment timing does not shift this anchor).
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

        $pickupDate = $order->getPickupDate();
        if ($pickupDate === null) {
            return $paidAt->setTimezone(new \DateTimeZone(self::APP_TZ));
        }

        return $this->buildPickupAnchor($order);
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
     * Build pickupDate at pickupTimeFrom (or 09:00 default), in Europe/Riga.
     */
    private function buildPickupAnchor(Order $order): \DateTimeImmutable
    {
        $tz = new \DateTimeZone(self::APP_TZ);
        $date = $order->getPickupDate();
        \assert($date !== null);

        $time = $order->getPickupTimeFrom()?->format('H:i') ?? self::DEFAULT_PICKUP_TIME;

        $anchor = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i',
            $date->format('Y-m-d') . ' ' . $time,
            $tz,
        );

        if ($anchor === false) {
            $anchor = (new \DateTimeImmutable($date->format('Y-m-d') . ' ' . self::DEFAULT_PICKUP_TIME, $tz));
        }

        return $anchor;
    }
}

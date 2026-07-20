<?php

declare(strict_types=1);

namespace App\Service\Order;

use App\Entity\Carrier;
use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderHistoryRepository;
use App\Repository\OrderRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds floating “ongoing orders” widget props for sender / carrier portal layouts.
 * Orders disappear once status is APPROVED (admin).
 */
final class PortalOngoingOrdersService
{
    private const int LIMIT = 20;

    /** Delivery-phase statuses still visible until Approved. */
    private const array ONGOING_STATUSES = [
        Order::STATUS['PAID'],
        Order::STATUS['ASSIGNED'],
        Order::STATUS['AWAITING_PICKUP'],
        Order::STATUS['PICKUP_DONE'],
        Order::STATUS['IN_TRANSIT'],
        Order::STATUS['DELIVERED'],
    ];

    public function __construct(
        private readonly Security $security,
        private readonly OrderRepository $orderRepository,
        private readonly OrderHistoryRepository $orderHistoryRepository,
        private readonly DeliveryDeadlineCalculator $deliveryDeadlineCalculator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{orders: list<array<string, mixed>>, isCarrier: bool}
     */
    public function buildForCurrentUser(): array
    {
        $user = $this->security->getUser();

        if ($user instanceof Carrier) {
            return [
                'orders' => $this->serializeOrders(
                    $this->orderRepository->findOngoingByCarrier($user, self::ONGOING_STATUSES, self::LIMIT),
                    $user->getLocale() ?? 'en',
                ),
                'isCarrier' => true,
            ];
        }

        if ($user instanceof User) {
            return [
                'orders' => $this->serializeOrders(
                    $this->orderRepository->findOngoingBySender($user, self::ONGOING_STATUSES, self::LIMIT),
                    $user->getLocale() ?? 'en',
                ),
                'isCarrier' => false,
            ];
        }

        return ['orders' => [], 'isCarrier' => false];
    }

    /**
     * @param list<Order> $orders
     *
     * @return list<array<string, mixed>>
     */
    private function serializeOrders(array $orders, string $locale): array
    {
        $items = [];
        foreach ($orders as $order) {
            $status = $order->getStatus();
            if ($status === null || $status === Order::STATUS['APPROVED']) {
                continue;
            }

            $deliveredHistory = $this->orderHistoryRepository->findLatestForOrderAndStatus(
                $order,
                Order::STATUS['DELIVERED'],
            );

            $items[] = [
                'id' => $order->getId()?->toRfc4122(),
                'name' => sprintf('Order %s', $order->getOrderNumber() ?? $order->getReference()),
                'status' => $this->translator->trans(
                    'order.status_' . $status,
                    domain: 'AppBundle',
                    locale: $locale,
                ),
                'delivered' => $status === Order::STATUS['DELIVERED'],
                'pickup_ready_at' => $this->deliveryDeadlineCalculator->resolveAnchor($order)?->format(\DateTimeInterface::ATOM),
                'deadline_at' => $this->deliveryDeadlineCalculator->resolveDeadline($order)?->format(\DateTimeInterface::ATOM),
                'delivered_date' => $deliveredHistory?->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return $items;
    }
}

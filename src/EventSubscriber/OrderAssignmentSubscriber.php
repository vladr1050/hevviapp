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

namespace App\EventSubscriber;

use App\Entity\Order;
use App\Entity\OrderAssignment;
use App\Notification\NotificationEventKey;
use App\Service\Notification\NotificationDispatchService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

/**
 * EventSubscriber для отслеживания жизненного цикла OrderAssignment.
 *
 * Триггеры уведомлений (Carrier-facing):
 * - postPersist OrderAssignment(status=ASSIGNED) → ORDER_ASSIGNED_TO_CARRIER
 *   (carrier получает извещение о новом запросе ДО того, как подтвердит).
 *
 * Синхронизация связанного Order:
 * - preUpdate OrderAssignment(status=ACCEPTED): carrier→Order, status→AWAITING_PICKUP.
 * - preUpdate OrderAssignment(status=REJECTED): carrier=null, status→PAID.
 */
#[AsDoctrineListener(event: Events::preUpdate, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::postPersist, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::postFlush, priority: 500, connection: 'default')]
class OrderAssignmentSubscriber
{
    private array $pendingOrderUpdates = [];
    /** @var Order[] */
    private array $pendingCarrierNotifications = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly NotificationDispatchService $notificationDispatchService,
    ) {
    }

    /**
     * Извещение carrier-у о новом запросе сразу после создания OrderAssignment
     * со статусом ASSIGNED (то есть до его подтверждения).
     */
    public function postPersist(PostPersistEventArgs $event): void
    {
        $entity = $event->getObject();

        if (!$entity instanceof OrderAssignment) {
            return;
        }

        if ($entity->getStatus() !== OrderAssignment::STATUS['ASSIGNED']) {
            return;
        }

        $order = $entity->getRelatedOrder();
        if ($order === null) {
            $this->logger->warning('OrderAssignment ASSIGNED создано без relatedOrder, уведомление перевозчику не отправлено', [
                'assignment_id' => $entity->getId()?->toRfc4122(),
            ]);
            return;
        }

        $this->logger->info('OrderAssignment ASSIGNED — ставим в очередь уведомление перевозчику', [
            'order_id' => $order->getId()?->toRfc4122(),
            'assignment_id' => $entity->getId()?->toRfc4122(),
            'carrier_id' => $entity->getCarrier()?->getId()?->toRfc4122(),
        ]);

        $this->pendingCarrierNotifications[] = $order;
    }

    /**
     * Обрабатывает событие preUpdate для OrderAssignment
     *
     * @param PreUpdateEventArgs $event
     * @return void
     */
    public function preUpdate(PreUpdateEventArgs $event): void
    {
        $entity = $event->getObject();

        // Обрабатываем только сущность OrderAssignment
        if (!$entity instanceof OrderAssignment) {
            return;
        }

        $this->logger->info('OrderAssignmentSubscriber::preUpdate вызван', [
            'assignment_id' => $entity->getId()?->toRfc4122(),
            'changed_fields' => array_keys($event->getEntityChangeSet()),
        ]);

        // Проверяем, изменился ли статус
        if (!$event->hasChangedField('status')) {
            $this->logger->info('Статус OrderAssignment не изменился, пропускаем');
            return;
        }

        $oldStatus = $event->getOldValue('status');
        $newStatus = $event->getNewValue('status');

        $this->logger->info('Статус OrderAssignment изменился', [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        $order = $entity->getRelatedOrder();

        if ($order === null) {
            $this->logger->warning('OrderAssignment не имеет связанного Order', [
                'assignment_id' => $entity->getId()?->toRfc4122(),
            ]);
            return;
        }

        if ($newStatus === OrderAssignment::STATUS['ACCEPTED']) {
            $carrier = $entity->getCarrier();

            if ($carrier === null) {
                $this->logger->warning('OrderAssignment не имеет carrier', [
                    'assignment_id' => $entity->getId()?->toRfc4122(),
                ]);
                return;
            }

            $this->logger->info('Carrier будет установлен в Order (ACCEPTED)', [
                'order_id' => $order->getId()?->toRfc4122(),
                'new_carrier_id' => $carrier->getId()?->toRfc4122(),
                'carrier_legal_name' => $carrier->getLegalName(),
            ]);

            $this->pendingOrderUpdates[] = [
                'order'   => $order,
                'carrier' => $carrier,
                'status'  => Order::STATUS['AWAITING_PICKUP'],
            ];

            return;
        }

        if ($newStatus === OrderAssignment::STATUS['REJECTED']) {
            $this->logger->info('Assignment REJECTED — carrier будет сброшен в Order', [
                'order_id'      => $order->getId()?->toRfc4122(),
                'old_status'    => $oldStatus,
            ]);

            $this->pendingOrderUpdates[] = [
                'order'   => $order,
                'carrier' => null,
                'status'  => Order::STATUS['PAID'],
            ];

            return;
        }

        $this->logger->info('Новый статус не требует обновления Order, пропускаем');
    }

    /**
     * Обрабатывает событие postFlush для сохранения изменений Order
     *
     * @param PostFlushEventArgs $event
     * @return void
     */
    public function postFlush(\Doctrine\ORM\Event\PostFlushEventArgs $event): void
    {
        $hasOrderUpdates = !empty($this->pendingOrderUpdates);
        $hasCarrierNotifications = !empty($this->pendingCarrierNotifications);

        if (!$hasOrderUpdates && !$hasCarrierNotifications) {
            return;
        }

        if ($hasOrderUpdates) {
            $this->logger->info('postFlush: Обрабатываем обновления Order', [
                'count' => count($this->pendingOrderUpdates),
            ]);

            $em = $event->getObjectManager();
            $updates = $this->pendingOrderUpdates;
            $this->pendingOrderUpdates = [];

            foreach ($updates as $update) {
                $order   = $update['order'];
                $carrier = $update['carrier'];
                $status  = $update['status'];

                $order->setCarrier($carrier);
                $order->setStatus($status);
                $em->persist($order);

                $this->logger->info('Order обновлён после изменения статуса Assignment', [
                    'order_id'   => $order->getId()?->toRfc4122(),
                    'carrier_id' => $carrier?->getId()?->toRfc4122() ?? 'null',
                    'new_status' => $status,
                ]);
            }

            $em->flush();
        }

        if ($hasCarrierNotifications) {
            $notifications = $this->pendingCarrierNotifications;
            $this->pendingCarrierNotifications = [];

            foreach ($notifications as $order) {
                try {
                    $this->notificationDispatchService->dispatch(
                        $order,
                        NotificationEventKey::ORDER_ASSIGNED_TO_CARRIER
                    );
                } catch (\Throwable $e) {
                    $this->logger->error('ORDER_ASSIGNED_TO_CARRIER notification dispatch failed', [
                        'order_id' => $order->getId()?->toRfc4122(),
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}

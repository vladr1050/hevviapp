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
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

/**
 * EventSubscriber для отслеживания изменений статуса OrderAssignment
 *
 * Когда статус OrderAssignment изменяется на ACCEPTED:
 * - Устанавливает carrier из OrderAssignment в связанный Order
 *
 * Принципы:
 * - Single Responsibility: Отвечает только за синхронизацию carrier между OrderAssignment и Order
 * - Open/Closed: Легко расширяется для добавления новой логики при изменении статуса
 */
#[AsDoctrineListener(event: Events::preUpdate, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::postFlush, priority: 500, connection: 'default')]
class OrderAssignmentSubscriber
{
    private array $pendingOrderUpdates = [];

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
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

        // Проверяем, изменился ли статус на ACCEPTED
        if ($newStatus !== OrderAssignment::STATUS['ACCEPTED']) {
            $this->logger->info('Новый статус не ACCEPTED, пропускаем');
            return;
        }

        // Получаем связанный Order и Carrier
        $order = $entity->getRelatedOrder();
        $carrier = $entity->getCarrier();

        if ($order === null) {
            $this->logger->warning('OrderAssignment не имеет связанного Order', [
                'assignment_id' => $entity->getId()?->toRfc4122(),
            ]);
            return;
        }

        if ($carrier === null) {
            $this->logger->warning('OrderAssignment не имеет carrier', [
                'assignment_id' => $entity->getId()?->toRfc4122(),
            ]);
            return;
        }

        $oldCarrier = $order->getCarrier();

        $this->logger->info('Carrier будет установлен в Order', [
            'order_id' => $order->getId()?->toRfc4122(),
            'old_carrier_id' => $oldCarrier?->getId()?->toRfc4122(),
            'new_carrier_id' => $carrier->getId()?->toRfc4122(),
            'carrier_legal_name' => $carrier->getLegalName(),
        ]);

        // Сохраняем изменение для postFlush (как в OrderHistorySubscriber)
        $this->pendingOrderUpdates[] = ['order' => $order, 'carrier' => $carrier];
    }

    /**
     * Обрабатывает событие postFlush для сохранения изменений Order
     *
     * @param PostFlushEventArgs $event
     * @return void
     */
    public function postFlush(\Doctrine\ORM\Event\PostFlushEventArgs $event): void
    {
        if (empty($this->pendingOrderUpdates)) {
            return;
        }

        $this->logger->info('postFlush: Обрабатываем обновления Order', [
            'count' => count($this->pendingOrderUpdates),
        ]);

        $em = $event->getObjectManager();
        $updates = $this->pendingOrderUpdates;
        $this->pendingOrderUpdates = [];

        foreach ($updates as $update) {
            $order = $update['order'];
            $carrier = $update['carrier'];

            $order->setCarrier($carrier);
            $order->setStatus(Order::STATUS['ASSIGNED']);
            $em->persist($order);

            $this->logger->info('Carrier установлен в Order и сохранен', [
                'order_id' => $order->getId()?->toRfc4122(),
                'carrier_id' => $carrier->getId()?->toRfc4122(),
                'carrier_legal_name' => $carrier->getLegalName(),
            ]);
        }

        $em->flush();
    }
}

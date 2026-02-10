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

use App\Entity\Carrier;
use App\Entity\Manager;
use App\Entity\Order;
use App\Entity\OrderHistory;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Common\EventSubscriber;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
class OrderHistorySubscriber implements EventSubscriber
{
    private array $pendingHistories = [];

    public function __construct(
        private readonly Security $security,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::preUpdate,
            Events::postFlush,
        ];
    }

    public function preUpdate(PreUpdateEventArgs $event): void
    {
        $entity = $event->getObject();

        // Обрабатываем только сущность Order
        if (!$entity instanceof Order) {
            return;
        }

        $this->logger->info('OrderHistorySubscriber::preUpdate вызван', [
            'order_id' => $entity->getId()?->toRfc4122(),
            'changed_fields' => array_keys($event->getEntityChangeSet()),
        ]);

        // Проверяем, изменился ли статус заказа
        if (!$event->hasChangedField('status')) {
            $this->logger->info('Статус не изменился, пропускаем');
            return;
        }

        $this->logger->info('Статус изменился, создаем историю');

        // Создаем новую запись в истории
        $history = new OrderHistory();
        $history->setRelatedOrder($entity);
        $history->setStatus($entity->getStatus());
        $history->setChangedBy($this->determineChangedByType());

        // Добавляем мета-информацию
        $history->setMeta([
            'old_status' => $event->getOldValue('status'),
            'new_status' => $event->getNewValue('status'),
            'changed_at' => new \DateTimeImmutable(),
        ]);

        // Сохраняем для postFlush
        $this->pendingHistories[] = $history;
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        if (empty($this->pendingHistories)) {
            return;
        }

        $this->logger->info('postFlush: Сохраняем истории', [
            'count' => count($this->pendingHistories),
        ]);

        $em = $event->getObjectManager();
        $histories = $this->pendingHistories;
        $this->pendingHistories = [];

        foreach ($histories as $history) {
            $em->persist($history);
        }

        $em->flush();
    }

    /**
     * Определяет тип изменившего на основе текущего пользователя
     */
    private function determineChangedByType(): int
    {
        $user = $this->security->getUser();

        if ($user === null) {
            // Никто не залогинен - система
            return OrderHistory::CHANGED_BY['SYSTEM'];
        }

        // Определяем тип пользователя по классу
        return match (true) {
            $user instanceof Manager => OrderHistory::CHANGED_BY['MANUAL'],
            $user instanceof User => OrderHistory::CHANGED_BY['USER'],
            $user instanceof Carrier => OrderHistory::CHANGED_BY['CARRIER'],
            default => OrderHistory::CHANGED_BY['SYSTEM'],
        };
    }
}

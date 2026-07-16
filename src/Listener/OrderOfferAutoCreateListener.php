<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Listener;

use App\Entity\Order;
use App\Service\OrderOffer\OrderOfferAutoCreateService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

/**
 * OrderOffer Auto Create Listener
 *
 * Автоматически создает OrderOffer для Order после сохранения.
 */
#[AsEntityListener(event: Events::postPersist, entity: Order::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Order::class)]
class OrderOfferAutoCreateListener
{
    public function __construct(
        private readonly OrderOfferAutoCreateService $offerAutoCreateService,
        private readonly LoggerInterface             $logger,
    ) {
    }

    public function postPersist(Order $order, PostPersistEventArgs $args): void
    {
        $this->logger->info('🆕 Order postPersist event triggered', [
            'order_id' => $order->getId()?->toRfc4122(),
        ]);

        $this->offerAutoCreateService->createIfNeeded($order, $args->getObjectManager());
    }

    public function postUpdate(Order $order, PostUpdateEventArgs $args): void
    {
        $this->logger->info('🔄 Order postUpdate event triggered', [
            'order_id' => $order->getId()?->toRfc4122(),
        ]);

        $this->offerAutoCreateService->createIfNeeded($order, $args->getObjectManager());
    }
}

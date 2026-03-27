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

namespace App\Listener;

use App\Entity\Order;
use App\Service\Order\Contract\OrderNumberGeneratorInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

/**
 * Автоматически присваивает человекочитаемый номер новому заказу.
 *
 * Срабатывает один раз — при создании Order.
 * Не перезаписывает уже установленный номер (защита от повторного вызова).
 */
#[AsEntityListener(event: Events::prePersist, entity: Order::class)]
final class OrderNumberListener
{
    public function __construct(
        private readonly OrderNumberGeneratorInterface $generator,
    ) {
    }

    public function prePersist(Order $order, PrePersistEventArgs $args): void
    {
        if ($order->getOrderNumber() !== null) {
            return;
        }

        $order->setOrderNumber($this->generator->generate());
    }
}

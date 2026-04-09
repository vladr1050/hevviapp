<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationLog;
use App\Entity\NotificationRule;
use App\Entity\Order;
use App\Notification\NotificationLogStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationLog>
 */
class NotificationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationLog::class);
    }

    public function hasSuccessfulSendForRuleAndOrder(
        NotificationRule $rule,
        Order $order,
        string $eventKey,
    ): bool {
        $count = (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.notificationRule = :rule')
            ->andWhere('l.relatedOrder = :order')
            ->andWhere('l.eventKey = :eventKey')
            ->andWhere('l.status = :sent')
            ->setParameter('rule', $rule)
            ->setParameter('order', $order)
            ->setParameter('eventKey', $eventKey)
            ->setParameter('sent', NotificationLogStatus::SENT)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}

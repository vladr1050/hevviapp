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

    /**
     * @return list<NotificationLog>
     */
    public function findFailedForOrdersBetweenAndTestFlag(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        bool $isTest,
        int $limit,
    ): array {
        return $this->createQueryBuilder('l')
            ->innerJoin('l.relatedOrder', 'o')
            ->addSelect('o')
            ->andWhere('o.isTest = :isTest')
            ->andWhere('l.status = :failed')
            ->andWhere('l.createdAt >= :from')
            ->andWhere('l.createdAt < :to')
            ->setParameter('isTest', $isTest)
            ->setParameter('failed', NotificationLogStatus::FAILED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countFailedForOrdersBetweenAndTestFlag(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        bool $isTest,
    ): int {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->innerJoin('l.relatedOrder', 'o')
            ->andWhere('o.isTest = :isTest')
            ->andWhere('l.status = :failed')
            ->andWhere('l.createdAt >= :from')
            ->andWhere('l.createdAt < :to')
            ->setParameter('isTest', $isTest)
            ->setParameter('failed', NotificationLogStatus::FAILED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

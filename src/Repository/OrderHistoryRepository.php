<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderHistory>
 */
class OrderHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderHistory::class);
    }

    /**
     * Latest history row for the given order status (e.g. DELIVERED) — for notification placeholders.
     */
    public function findLatestForOrderAndStatus(Order $order, int $status): ?OrderHistory
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.relatedOrder = :order')
            ->andWhere('h.status = :status')
            ->setParameter('order', $order)
            ->setParameter('status', $status)
            ->orderBy('h.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * First transition to the given status (e.g. PAID = payment moment).
     */
    public function findEarliestForOrderAndStatus(Order $order, int $status): ?OrderHistory
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.relatedOrder = :order')
            ->andWhere('h.status = :status')
            ->setParameter('order', $order)
            ->setParameter('status', $status)
            ->orderBy('h.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Distinct orders (by is_test) that entered `$status` between `$from` (inclusive) and `$to` (exclusive).
     *
     * @return list<Order>
     */
    public function findOrdersWithStatusBetweenAndTestFlag(
        int $status,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        bool $isTest,
        int $limit,
    ): array {
        /** @var list<OrderHistory> $rows */
        $rows = $this->createQueryBuilder('h')
            ->innerJoin('h.relatedOrder', 'o')
            ->addSelect('o')
            ->andWhere('o.isTest = :isTest')
            ->andWhere('h.status = :status')
            ->andWhere('h.createdAt >= :from')
            ->andWhere('h.createdAt < :to')
            ->setParameter('isTest', $isTest)
            ->setParameter('status', $status)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('h.createdAt', 'DESC')
            ->setMaxResults($limit * 5)
            ->getQuery()
            ->getResult();

        $orders = [];
        foreach ($rows as $history) {
            $order = $history->getRelatedOrder();
            if ($order === null) {
                continue;
            }
            $id = $order->getId();
            if ($id === null) {
                continue;
            }
            $key = (string) $id;
            if (isset($orders[$key])) {
                continue;
            }
            $orders[$key] = $order;
            if (count($orders) >= $limit) {
                break;
            }
        }

        return array_values($orders);
    }

    public function countOrdersWithStatusBetweenAndTestFlag(
        int $status,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        bool $isTest,
    ): int {
        return (int) $this->createQueryBuilder('h')
            ->select('COUNT(DISTINCT o.id)')
            ->innerJoin('h.relatedOrder', 'o')
            ->andWhere('o.isTest = :isTest')
            ->andWhere('h.status = :status')
            ->andWhere('h.createdAt >= :from')
            ->andWhere('h.createdAt < :to')
            ->setParameter('isTest', $isTest)
            ->setParameter('status', $status)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    //    /**
    //     * @return OrderHistory[] Returns an array of OrderHistory objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?OrderHistory
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

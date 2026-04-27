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

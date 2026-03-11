<?php

namespace App\Repository;

use App\Entity\Carrier;
use App\Entity\Order;
use App\Entity\OrderAssignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderAssignment>
 */
class OrderAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderAssignment::class);
    }

    /**
     * Находит назначение конкретного перевозчика на конкретный заказ со статусом ASSIGNED.
     * Используется при обработке подтверждения или отклонения запроса перевозчиком.
     */
    public function findAssignedByOrderAndCarrier(Order $order, Carrier $carrier): ?OrderAssignment
    {
        return $this->findOneBy([
            'relatedOrder' => $order,
            'carrier'      => $carrier,
            'status'       => OrderAssignment::STATUS['ASSIGNED'],
        ]);
    }

    //    /**
    //     * @return OrderAssignment[] Returns an array of OrderAssignment objects
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

    //    public function findOneBySomeField($value): ?OrderAssignment
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

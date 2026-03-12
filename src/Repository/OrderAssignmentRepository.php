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

    /**
     * Возвращает количество назначений перевозчика со статусами ACCEPTED и REJECTED.
     * Используется для расчёта процента одобрения (apply rate) на странице профиля.
     *
     * @return array{accepted: int, rejected: int}
     */
    public function countAcceptedAndRejectedByCarrier(Carrier $carrier): array
    {
        $rows = $this->createQueryBuilder('oa')
            ->select('oa.status', 'COUNT(oa.id) as cnt')
            ->where('oa.carrier = :carrier')
            ->andWhere('oa.status IN (:statuses)')
            ->setParameter('carrier', $carrier)
            ->setParameter('statuses', [
                OrderAssignment::STATUS['ACCEPTED'],
                OrderAssignment::STATUS['REJECTED'],
            ])
            ->groupBy('oa.status')
            ->getQuery()
            ->getResult();

        $counts = ['accepted' => 0, 'rejected' => 0];

        foreach ($rows as $row) {
            match ((int) $row['status']) {
                OrderAssignment::STATUS['ACCEPTED'] => $counts['accepted'] = (int) $row['cnt'],
                OrderAssignment::STATUS['REJECTED']  => $counts['rejected'] = (int) $row['cnt'],
                default => null,
            };
        }

        return $counts;
    }
}

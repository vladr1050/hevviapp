<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * Подсчитать количество заказов сгруппированных по статусу.
     *
     * @return array<int, int> Массив где ключ - статус, значение - количество заказов
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('o')
            ->select('o.status', 'COUNT(o.id) as count')
            ->groupBy('o.status')
            ->getQuery()
            ->getResult();

        // Преобразуем результат в удобный формат [status => count]
        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }
}

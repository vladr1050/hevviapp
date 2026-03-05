<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
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
     * Возвращает последние заказы отправителя, отсортированные по дате создания.
     *
     * @return Order[]
     */
    public function findRecentBySender(User $user, int $limit = 10): array
    {
        return $this->findBy(
            ['sender' => $user],
            ['createdAt' => 'DESC'],
            $limit,
        );
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

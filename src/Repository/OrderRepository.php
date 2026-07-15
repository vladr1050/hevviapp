<?php

namespace App\Repository;

use App\Entity\Carrier;
use App\Entity\Order;
use App\Entity\OrderAssignment;
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
     * @return Order[]
     */
    public function findRecentBySenderExcludingStatuses(
        User $user,
        array $excludeStatuses,
        int $limit = 50,
        int $offset = 0,
    ): array {
        $qb = $this->createQueryBuilder('o')
            ->where('o.sender = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if ($excludeStatuses !== []) {
            $qb->andWhere('o.status NOT IN (:excludeStatuses)')
                ->setParameter('excludeStatuses', $excludeStatuses);
        }

        return $qb->getQuery()->getResult();
    }

    public function countBySenderExcludingStatuses(User $user, array $excludeStatuses): int
    {
        $qb = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.sender = :user')
            ->setParameter('user', $user);

        if ($excludeStatuses !== []) {
            $qb->andWhere('o.status NOT IN (:excludeStatuses)')
                ->setParameter('excludeStatuses', $excludeStatuses);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Возвращает последние заказы перевозчика, отсортированные по дате создания.
     *
     * @return Order[]
     */
    public function findRecentByCarrier(Carrier $carrier, int $limit = 10): array
    {
        return $this->findBy(
            ['carrier' => $carrier],
            ['createdAt' => 'DESC'],
            $limit,
        );
    }

    /**
     * Возвращает заказы, по которым текущий перевозчик имеет назначение
     * со статусом ASSIGNED — то есть входящие запросы, ожидающие его ответа.
     *
     * @return Order[]
     */
    public function findRequestsByCarrier(Carrier $carrier): array
    {
        return $this->createQueryBuilder('o')
            ->innerJoin('o.orderAssignments', 'oa')
            ->where('oa.carrier = :carrier')
            ->andWhere('oa.status = :status')
            ->setParameter('carrier', $carrier)
            ->setParameter('status', OrderAssignment::STATUS['ASSIGNED'])
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
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

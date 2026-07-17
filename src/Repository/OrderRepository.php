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
        ?array $includeStatuses = null,
        string $sortDirection = 'DESC',
    ): array {
        $qb = $this->createQueryBuilder('o')
            ->where('o.sender = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC' === strtoupper($sortDirection) ? 'DESC' : 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if (null !== $includeStatuses && [] !== $includeStatuses) {
            $qb->andWhere('o.status IN (:includeStatuses)')
                ->setParameter('includeStatuses', $includeStatuses);
        } elseif ($excludeStatuses !== []) {
            $qb->andWhere('o.status NOT IN (:excludeStatuses)')
                ->setParameter('excludeStatuses', $excludeStatuses);
        }

        return $qb->getQuery()->getResult();
    }

    public function countBySenderExcludingStatuses(
        User $user,
        array $excludeStatuses,
        ?array $includeStatuses = null,
    ): int {
        $qb = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.sender = :user')
            ->setParameter('user', $user);

        if (null !== $includeStatuses && [] !== $includeStatuses) {
            $qb->andWhere('o.status IN (:includeStatuses)')
                ->setParameter('includeStatuses', $includeStatuses);
        } elseif ($excludeStatuses !== []) {
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
     * @return Order[]
     */
    public function findRecentByCarrierExcludingStatuses(
        Carrier $carrier,
        array $excludeStatuses,
        int $limit = 50,
        int $offset = 0,
        ?array $includeStatuses = null,
        string $sortDirection = 'DESC',
    ): array {
        $qb = $this->createQueryBuilder('o')
            ->where('o.carrier = :carrier')
            ->setParameter('carrier', $carrier)
            ->orderBy('o.createdAt', 'DESC' === strtoupper($sortDirection) ? 'DESC' : 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if (null !== $includeStatuses && [] !== $includeStatuses) {
            $qb->andWhere('o.status IN (:includeStatuses)')
                ->setParameter('includeStatuses', $includeStatuses);
        } elseif ($excludeStatuses !== []) {
            $qb->andWhere('o.status NOT IN (:excludeStatuses)')
                ->setParameter('excludeStatuses', $excludeStatuses);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByCarrierExcludingStatuses(
        Carrier $carrier,
        array $excludeStatuses,
        ?array $includeStatuses = null,
    ): int {
        $qb = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.carrier = :carrier')
            ->setParameter('carrier', $carrier);

        if (null !== $includeStatuses && [] !== $includeStatuses) {
            $qb->andWhere('o.status IN (:includeStatuses)')
                ->setParameter('includeStatuses', $includeStatuses);
        } elseif ($excludeStatuses !== []) {
            $qb->andWhere('o.status NOT IN (:excludeStatuses)')
                ->setParameter('excludeStatuses', $excludeStatuses);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
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

    /**
     * Orders filtered by is_test, grouped by status.
     *
     * @return array<int, int>
     */
    public function countByStatusAndTestFlag(bool $isTest): array
    {
        $result = $this->createQueryBuilder('o')
            ->select('o.status', 'COUNT(o.id) as count')
            ->andWhere('o.isTest = :isTest')
            ->setParameter('isTest', $isTest)
            ->groupBy('o.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[(int) $row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * @return list<Order>
     */
    public function findByStatusOlderThanAndTestFlag(
        int $status,
        \DateTimeImmutable $olderThan,
        bool $isTest,
        int $limit,
    ): array {
        return $this->createQueryBuilder('o')
            ->andWhere('o.isTest = :isTest')
            ->andWhere('o.status = :status')
            ->andWhere('o.updatedAt < :olderThan')
            ->setParameter('isTest', $isTest)
            ->setParameter('status', $status)
            ->setParameter('olderThan', $olderThan)
            ->orderBy('o.updatedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByStatusOlderThanAndTestFlag(
        int $status,
        \DateTimeImmutable $olderThan,
        bool $isTest,
    ): int {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.isTest = :isTest')
            ->andWhere('o.status = :status')
            ->andWhere('o.updatedAt < :olderThan')
            ->setParameter('isTest', $isTest)
            ->setParameter('status', $status)
            ->setParameter('olderThan', $olderThan)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<int> $statuses
     *
     * @return list<Order>
     */
    public function findByStatusesAndTestFlag(array $statuses, bool $isTest, int $limit): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.isTest = :isTest')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('isTest', $isTest)
            ->setParameter('statuses', $statuses)
            ->orderBy('o.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Order>
     */
    public function findCreatedBetweenAndTestFlag(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        bool $isTest,
        int $limit,
    ): array {
        return $this->createQueryBuilder('o')
            ->andWhere('o.isTest = :isTest')
            ->andWhere('o.createdAt >= :from')
            ->andWhere('o.createdAt < :to')
            ->setParameter('isTest', $isTest)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countCreatedBetweenAndTestFlag(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        bool $isTest,
    ): int {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.isTest = :isTest')
            ->andWhere('o.createdAt >= :from')
            ->andWhere('o.createdAt < :to')
            ->setParameter('isTest', $isTest)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

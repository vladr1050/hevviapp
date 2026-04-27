<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\Order;
use App\Entity\OrderOffer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function findOneByOrderOffer(OrderOffer $offer): ?Invoice
    {
        return $this->findOneBy(['orderOffer' => $offer]);
    }

    public function findLatestWithPdfForOrder(Order $order): ?Invoice
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.relatedOrder = :order')
            ->andWhere('i.pdfRelativePath IS NOT NULL')
            ->andWhere('i.pdfRelativePath != :empty')
            ->setParameter('order', $order)
            ->setParameter('empty', '')
            ->orderBy('i.issueDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * First invoice for the order (by issue date) — used for ETA when status history is incomplete.
     */
    public function findEarliestByRelatedOrder(Order $order): ?Invoice
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.relatedOrder = :order')
            ->setParameter('order', $order)
            ->orderBy('i.issueDate', 'ASC')
            ->addOrderBy('i.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

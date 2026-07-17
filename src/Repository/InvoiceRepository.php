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

    public function findOneByPdfRelativePath(string $relativePath): ?Invoice
    {
        return $this->findOneBy(['pdfRelativePath' => $relativePath]);
    }

    /**
     * Sum invoice money for orders in the given is_test scope and issue-date range.
     * `$toExclusive` is exclusive (typically start of day after last included day).
     *
     * @return array{
     *     invoice_count: int,
     *     freight_net_cents: int,
     *     commission_net_cents: int,
     *     vat_cents: int,
     *     gross_cents: int
     * }
     */
    public function sumAmountsByIssueDateAndTestFlag(
        \DateTimeImmutable $from,
        \DateTimeImmutable $toExclusive,
        bool $isTest,
    ): array {
        $row = $this->createQueryBuilder('i')
            ->select(
                'COUNT(i.id) AS invoice_count',
                'COALESCE(SUM(i.amountFreight), 0) AS freight_net_cents',
                'COALESCE(SUM(i.amountCommission), 0) AS commission_net_cents',
                'COALESCE(SUM(i.amountVat), 0) AS vat_cents',
                'COALESCE(SUM(i.amountGross), 0) AS gross_cents',
            )
            ->innerJoin('i.relatedOrder', 'o')
            ->andWhere('o.isTest = :isTest')
            ->andWhere('i.issueDate >= :from')
            ->andWhere('i.issueDate < :to')
            ->setParameter('isTest', $isTest)
            ->setParameter('from', $from)
            ->setParameter('to', $toExclusive)
            ->getQuery()
            ->getSingleResult();

        return [
            'invoice_count' => (int) $row['invoice_count'],
            'freight_net_cents' => (int) $row['freight_net_cents'],
            'commission_net_cents' => (int) $row['commission_net_cents'],
            'vat_cents' => (int) $row['vat_cents'],
            'gross_cents' => (int) $row['gross_cents'],
        ];
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

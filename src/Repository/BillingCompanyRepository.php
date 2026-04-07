<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Repository;

use App\Entity\BillingCompany;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<BillingCompany>
 */
class BillingCompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BillingCompany::class);
    }

    public function findIssuingCompany(): ?BillingCompany
    {
        return $this->findOneBy(['issuesInvoices' => true]);
    }

    public function countIssuingCompanies(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.issuesInvoices = :true')
            ->setParameter('true', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Clears the "issues invoices" flag on all rows except the given id (if any).
     */
    public function demoteOtherIssuers(?Uuid $exceptId): void
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->update(BillingCompany::class, 'c')
            ->set('c.issuesInvoices', ':false')
            ->where('c.issuesInvoices = :true')
            ->setParameter('false', false)
            ->setParameter('true', true);

        if ($exceptId !== null) {
            $qb->andWhere('c.id <> :id')
                ->setParameter('id', $exceptId, UuidType::NAME);
        }

        $qb->getQuery()->execute();
    }
}

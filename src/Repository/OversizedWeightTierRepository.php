<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OversizedWeightTier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OversizedWeightTier>
 */
class OversizedWeightTierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OversizedWeightTier::class);
    }

    public function findOneByPallets(int $pallets): ?OversizedWeightTier
    {
        return $this->findOneBy(['pallets' => $pallets]);
    }

    /**
     * Highest configured pallet count, or null when the table is empty.
     */
    public function findMaxPallets(): ?int
    {
        $max = $this->createQueryBuilder('t')
            ->select('MAX(t.pallets)')
            ->getQuery()
            ->getSingleScalarResult();

        return $max !== null ? (int) $max : null;
    }
}

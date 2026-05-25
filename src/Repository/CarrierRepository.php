<?php

namespace App\Repository;

use App\Entity\Carrier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Carrier>
 */
class CarrierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Carrier::class);
    }

    public function findDefaultForPricing(): ?Carrier
    {
        return $this->findOneBy(['isDefaultForPricing' => true]);
    }

    /**
     * Clears default-for-pricing on all carriers except the given id (if any).
     */
    public function demoteOtherDefaultForPricing(?Uuid $exceptId): void
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->update(Carrier::class, 'c')
            ->set('c.isDefaultForPricing', ':false')
            ->where('c.isDefaultForPricing = :true')
            ->setParameter('false', false)
            ->setParameter('true', true);

        if ($exceptId !== null) {
            $qb->andWhere('c.id <> :id')
                ->setParameter('id', $exceptId, UuidType::NAME);
        }

        $qb->getQuery()->execute();
    }

    //    /**
    //     * @return Carrier[] Returns an array of Carrier objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Carrier
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

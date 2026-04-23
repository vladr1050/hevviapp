<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TermsOfUseRevision;
use App\Enum\TermsAudience;
use App\Enum\TermsRevisionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<TermsOfUseRevision>
 */
class TermsOfUseRevisionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TermsOfUseRevision::class);
    }

    public function getNextVersion(TermsAudience $audience): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COALESCE(MAX(t.version), 0)')
            ->where('t.audience = :audience')
            ->setParameter('audience', $audience);

        $max = (int) $qb->getQuery()->getSingleScalarResult();

        return $max + 1;
    }

    public function findCurrentPublished(TermsAudience $audience): ?TermsOfUseRevision
    {
        return $this->createQueryBuilder('t')
            ->where('t.audience = :audience')
            ->andWhere('t.status = :published')
            ->setParameter('audience', $audience)
            ->setParameter('published', TermsRevisionStatus::Published)
            ->orderBy('t.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Marks every other published revision for this audience as superseded.
     */
    public function supersedeOtherPublished(TermsAudience $audience, Uuid $keepId): int
    {
        return (int) $this->createQueryBuilder('t')
            ->update()
            ->set('t.status', ':superseded')
            ->where('t.audience = :audience')
            ->andWhere('t.status = :published')
            ->andWhere('t.id != :keepId')
            ->setParameter('audience', $audience)
            ->setParameter('published', TermsRevisionStatus::Published)
            ->setParameter('superseded', TermsRevisionStatus::Superseded)
            ->setParameter('keepId', $keepId, 'uuid')
            ->getQuery()
            ->execute();
    }
}

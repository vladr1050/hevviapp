<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WaitingListApplicant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WaitingListApplicant>
 */
class WaitingListApplicantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WaitingListApplicant::class);
    }

    public function findOneByNormalizedEmail(string $email): ?WaitingListApplicant
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return null;
        }

        return $this->findOneBy(['email' => $normalized]);
    }
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PortalLoginConsentLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PortalLoginConsentLog>
 */
class PortalLoginConsentLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PortalLoginConsentLog::class);
    }
}

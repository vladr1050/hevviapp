<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PricingSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PricingSettings>
 */
class PricingSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PricingSettings::class);
    }

    public function getSingleton(): ?PricingSettings
    {
        return $this->findOneBy([]);
    }
}

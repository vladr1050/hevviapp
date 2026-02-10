<?php

namespace App\Repository;

use App\Entity\GeoArea;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GeoArea>
 */
class GeoAreaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GeoArea::class);
    }

    /**
     * Получить все страны (scope = 1)
     * 
     * @return GeoArea[]
     */
    public function findAllCountries(): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.scope = :scope')
            ->setParameter('scope', GeoArea::SCOPE['COUNTRY'])
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить все города по ISO3 коду страны
     * 
     * @param string $countryISO3
     * @return GeoArea[]
     */
    public function findCitiesByCountryISO3(string $countryISO3): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.scope = :scope')
            ->andWhere('g.countryISO3 = :countryISO3')
            ->setParameter('scope', GeoArea::SCOPE['CITY'])
            ->setParameter('countryISO3', $countryISO3)
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить гео-зону с геометрией по ID
     * 
     * @param string $id
     * @return GeoArea|null
     */
    public function findOneWithGeometry(string $id): ?GeoArea
    {
        return $this->createQueryBuilder('g')
            ->where('g.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

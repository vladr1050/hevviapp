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

    /**
     * Получить все кастомные зоны по ISO3 коду страны
     * 
     * @param string $countryISO3
     * @return GeoArea[]
     */
    public function findCustomAreasByCountryISO3(string $countryISO3): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.scope = :scope')
            ->andWhere('g.countryISO3 = :countryISO3')
            ->setParameter('scope', GeoArea::SCOPE['CUSTOM_AREA'])
            ->setParameter('countryISO3', $countryISO3)
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Все админ-юниты одного scope в стране (например все novadi или все pagasti).
     *
     * @return GeoArea[]
     */
    public function findByScopeAndCountryISO3(int $scope, string $countryISO3): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.scope = :scope')
            ->andWhere('g.countryISO3 = :countryISO3')
            ->setParameter('scope', $scope)
            ->setParameter('countryISO3', $countryISO3)
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * GeoArea дочернего scope, чьи геометрии лежат внутри геометрии указанного родителя.
     *
     * Используется для каскада «выбрали novads → показать его pagasti».
     * Используется ST_CoveredBy (захватывает кейсы, когда границы точно совпадают).
     *
     * @return array<int, array{id: string, name: string, country_iso3: string, scope: int}>
     */
    public function findChildrenWithinParent(string $parentId, int $childScope): array
    {
        $sql = '
            SELECT child.id::text AS id, child.name, child.country_iso3, child.scope
            FROM geo_area child
            INNER JOIN geo_area parent ON parent.id::text = :parent_id
            WHERE child.scope = :child_scope
              AND child.id <> parent.id
              AND ST_CoveredBy(child.geometry, parent.geometry)
            ORDER BY child.name ASC
        ';

        $stmt = $this->getEntityManager()->getConnection()->executeQuery(
            $sql,
            [
                'parent_id' => $parentId,
                'child_scope' => $childScope,
            ],
        );

        return $stmt->fetchAllAssociative();
    }
}

<?php

namespace App\Repository;

use App\Entity\Carrier;
use App\Entity\GeoArea;
use App\Entity\ServiceArea;
use App\Service\OrderOffer\Pricing\DTO\CoordinateCoverageResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ServiceArea>
 */
class ServiceAreaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceArea::class);
    }

    /**
     * Найти ServiceArea по координатам точки доставки используя PostGIS.
     * 
     * Метод ищет ServiceArea, у которого хотя бы одна связанная GeoArea содержит 
     * указанную точку в своей геометрии (MULTIPOLYGON).
     * 
     * ВАЖНО: Для оптимальной производительности необходимо создать GIST индекс на geo_area.geometry:
     * CREATE INDEX idx_geo_area_geometry ON geo_area USING GIST(geometry);
     * 
     * @param float $latitude  Широта точки доставки
     * @param float $longitude Долгота точки доставки
     * 
     * @return ServiceArea|null Найденная ServiceArea или null если не найдена
     */
    public function findByCoordinates(float $latitude, float $longitude, ?Carrier $carrier = null): ?ServiceArea
    {
        return $this->findCoverageAtCoordinates($latitude, $longitude, $carrier)?->serviceArea;
    }

    /**
     * Resolves both the containing GeoArea and the carrier ServiceArea whose matrix applies at a point.
     */
    public function findCoverageAtCoordinates(
        float $latitude,
        float $longitude,
        ?Carrier $carrier = null,
    ): ?CoordinateCoverageResult {
        $row = $this->fetchCoverageRow($latitude, $longitude, $carrier);
        if ($row === null) {
            return null;
        }

        $geoArea = $this->getEntityManager()->find(GeoArea::class, $row['geo_area_id']);
        $serviceArea = $this->loadServiceAreaWithMatrix($row['service_area_id']);

        if (!$geoArea instanceof GeoArea || !$serviceArea instanceof ServiceArea) {
            return null;
        }

        return new CoordinateCoverageResult($geoArea, $serviceArea);
    }

    /**
     * @return array{geo_area_id: string, service_area_id: string}|null
     */
    private function fetchCoverageRow(float $latitude, float $longitude, ?Carrier $carrier): ?array
    {
        $conn = $this->getEntityManager()->getConnection();

        $point = sprintf('POINT(%f %f)', $longitude, $latitude);

        $carrierFilter = '';
        $params = ['point' => $point];
        if ($carrier !== null && $carrier->getId() !== null) {
            $carrierFilter = ' AND sa.carrier_id = :carrier_id';
            $params['carrier_id'] = $carrier->getId()->toRfc4122();
        } else {
            $carrierFilter = ' AND sa.carrier_id IS NULL';
        }

        $sql = '
            SELECT ga.id AS geo_area_id, sa.id AS service_area_id
            FROM service_area sa
            INNER JOIN service_area_geo_area saga ON sa.id = saga.service_area_id
            INNER JOIN geo_area ga ON saga.geo_area_id = ga.id
            WHERE ST_Contains(
                ga.geometry,
                ST_SetSRID(ST_GeomFromText(:point), 4326)
            )
            '.$carrierFilter.'
            LIMIT 1
        ';

        $result = $conn->executeQuery($sql, $params);
        $row = $result->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return [
            'geo_area_id' => (string) $row['geo_area_id'],
            'service_area_id' => (string) $row['service_area_id'],
        ];
    }

    public function findHomeZoneForCarrier(Carrier $carrier, string $country): ?ServiceArea
    {
        return $this->createQueryBuilder('sa')
            ->leftJoin('sa.matrixItems', 'mi')
            ->addSelect('mi')
            ->where('sa.carrier = :carrier')
            ->andWhere('sa.country = :country')
            ->andWhere('sa.isHomeZone = true')
            ->setParameter('carrier', $carrier)
            ->setParameter('country', strtoupper($country))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Clears is_home_zone on other service areas for the same carrier + country.
     */
    public function demoteOtherHomeZones(Carrier $carrier, string $country, ?Uuid $exceptId): void
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->update(ServiceArea::class, 'sa')
            ->set('sa.isHomeZone', ':false')
            ->where('sa.carrier = :carrier')
            ->andWhere('sa.country = :country')
            ->andWhere('sa.isHomeZone = :true')
            ->setParameter('false', false)
            ->setParameter('true', true)
            ->setParameter('carrier', $carrier)
            ->setParameter('country', strtoupper($country));

        if ($exceptId !== null) {
            $qb->andWhere('sa.id <> :id')
                ->setParameter('id', $exceptId, UuidType::NAME);
        }

        $qb->getQuery()->execute();
    }

    private function loadServiceAreaWithMatrix(mixed $id): ?ServiceArea
    {
        return $this->createQueryBuilder('sa')
            ->leftJoin('sa.matrixItems', 'mi')
            ->addSelect('mi')
            ->where('sa.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

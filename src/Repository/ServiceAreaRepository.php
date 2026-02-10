<?php

namespace App\Repository;

use App\Entity\ServiceArea;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
    public function findByCoordinates(float $latitude, float $longitude): ?ServiceArea
    {
        $conn = $this->getEntityManager()->getConnection();
        
        // Создаем точку в формате WKT (Well-Known Text) для PostGIS
        // SRID 4326 - это стандартная система координат WGS84 (используется в GPS)
        $point = sprintf('POINT(%f %f)', $longitude, $latitude);
        
        // SQL запрос с использованием PostGIS функции ST_Contains
        // ST_Contains проверяет, содержится ли точка внутри полигона
        $sql = '
            SELECT DISTINCT sa.id
            FROM service_area sa
            INNER JOIN service_area_geo_area saga ON sa.id = saga.service_area_id
            INNER JOIN geo_area ga ON saga.geo_area_id = ga.id
            WHERE ST_Contains(
                ga.geometry,
                ST_SetSRID(ST_GeomFromText(:point), 4326)
            )
            LIMIT 1
        ';
        
        $result = $conn->executeQuery($sql, [
            'point' => $point,
        ]);
        
        $row = $result->fetchAssociative();
        
        if (!$row) {
            return null;
        }
        
        // Загружаем полную сущность ServiceArea с её связями
        return $this->createQueryBuilder('sa')
            ->leftJoin('sa.matrixItems', 'mi')
            ->addSelect('mi')
            ->where('sa.id = :id')
            ->setParameter('id', $row['id'])
            ->getQuery()
            ->getOneOrNullResult();
    }
}

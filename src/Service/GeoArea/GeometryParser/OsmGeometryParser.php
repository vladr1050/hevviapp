<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains
 * the property of SIA SLYFOX, its suppliers and Customers,
 * if any.  The intellectual and technical concepts contained
 * herein are proprietary to SIA SLYFOX
 * its Suppliers and Customers are protected by trade secret or copyright law.
 *
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained.
 */

namespace App\Service\GeoArea\GeometryParser;

use App\Service\GeoArea\Contract\GeometryParserInterface;

/**
 * Парсер для конвертации геометрии из GeoJSON в WKT MULTIPOLYGON
 */
class OsmGeometryParser implements GeometryParserInterface
{
    public function geoJsonToWkt(array $geoJson): string
    {
        if (!isset($geoJson['geometry'])) {
            throw new \InvalidArgumentException('GeoJSON must contain geometry field');
        }

        $geometry = $geoJson['geometry'];
        $type = $geometry['type'] ?? null;

        return match ($type) {
            'Polygon' => $this->convertPolygonToMultiPolygon($geometry['coordinates']),
            'MultiPolygon' => $this->convertMultiPolygon($geometry['coordinates']),
            default => throw new \InvalidArgumentException("Unsupported geometry type: {$type}"),
        };
    }

    /**
     * Конвертировать Polygon в MULTIPOLYGON WKT
     */
    private function convertPolygonToMultiPolygon(array $coordinates): string
    {
        return 'MULTIPOLYGON(' . $this->formatPolygon($coordinates) . ')';
    }

    /**
     * Конвертировать MultiPolygon в MULTIPOLYGON WKT
     */
    private function convertMultiPolygon(array $coordinates): string
    {
        $polygons = [];
        
        foreach ($coordinates as $polygon) {
            $polygons[] = $this->formatPolygon($polygon);
        }

        return 'MULTIPOLYGON(' . implode(',', $polygons) . ')';
    }

    /**
     * Форматировать полигон в WKT формат
     */
    private function formatPolygon(array $rings): string
    {
        $formattedRings = [];
        
        foreach ($rings as $ring) {
            if (!$this->isValidRing($ring)) {
                throw new \InvalidArgumentException('Polygon ring must have at least 4 distinct points');
            }

            $points = [];
            
            foreach ($ring as $point) {
                if (!isset($point[0], $point[1])) {
                    throw new \InvalidArgumentException('Invalid point coordinates');
                }
                
                $points[] = sprintf('%F %F', $point[0], $point[1]);
            }
            
            $formattedRings[] = '(' . implode(',', $points) . ')';
        }

        return '(' . implode(',', $formattedRings) . ')';
    }

    /**
     * PostGIS requires at least 4 points per ring; OSM sometimes yields degenerate boundaries.
     */
    private function isValidRing(array $ring): bool
    {
        if (count($ring) < 4) {
            return false;
        }

        $distinct = [];
        foreach ($ring as $point) {
            if (!isset($point[0], $point[1])) {
                return false;
            }
            $distinct[sprintf('%.6F,%.6F', (float)$point[0], (float)$point[1])] = true;
        }

        if (count($distinct) < 3) {
            return false;
        }

        $lons = array_map(static fn(array $p): float => (float)$p[0], $ring);
        $lats = array_map(static fn(array $p): float => (float)$p[1], $ring);

        if (max($lons) === min($lons) && max($lats) === min($lats)) {
            return false;
        }

        // OSM иногда отдаёт «заглушку» (1,1) или точку вне Латвии для pagasts без polygon boundary.
        $minLon = 20.0;
        $maxLon = 29.0;
        $minLat = 55.0;
        $maxLat = 59.0;

        return max($lons) >= $minLon
            && min($lons) <= $maxLon
            && max($lats) >= $minLat
            && min($lats) <= $maxLat;
    }
}

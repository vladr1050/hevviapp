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
}

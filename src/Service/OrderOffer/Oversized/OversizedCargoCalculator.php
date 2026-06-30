<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Oversized;

/**
 * Geometry helper for oversized cargo (industry order: Length × Width × Height).
 *
 * Standard pallet footprint: 120 cm (L) × 80 cm (W).
 * Standard pallet height envelope: 188 cm (H).
 * Maximum allowed cargo height for all types: 250 cm.
 */
final class OversizedCargoCalculator
{
    public const float PALLET_LENGTH_CM = 120.0;

    public const float PALLET_WIDTH_CM = 80.0;

    public const float PALLET_HEIGHT_CM = 188.0;

    public const float PALLET_AREA_CM2 = self::PALLET_LENGTH_CM * self::PALLET_WIDTH_CM;

    public const float MAX_CARGO_HEIGHT_CM = 250.0;

    /** A dimension must exceed its limit by at least this (cm) to count. */
    private const float DIMENSION_TOLERANCE_CM = 0.01;

    /** Area must exceed a pallet footprint by at least this (cm²) to count as an extra place. */
    private const float AREA_TOLERANCE_CM2 = 0.01;

    private const float EPSILON = 1.0e-9;

    /**
     * Parse "LxWxH" centimeter string (also accepts comma separators).
     *
     * @return array{length: float, width: float, height: float}|null sides in centimeters
     */
    public function parseDimensionsCm(?string $dimensionsCm): ?array
    {
        if ($dimensionsCm === null || trim($dimensionsCm) === '') {
            return null;
        }

        $parts = preg_split('/[x,Х×*]/iu', trim($dimensionsCm)) ?: [];
        if (count($parts) !== 3) {
            return null;
        }

        $values = [];
        foreach ($parts as $part) {
            $normalized = str_replace(',', '.', trim($part));
            if (!is_numeric($normalized)) {
                return null;
            }
            $cm = (float) $normalized;
            if ($cm <= 0.0) {
                return null;
            }
            $values[] = $cm;
        }

        return [
            'length' => $values[0],
            'width' => $values[1],
            'height' => $values[2],
        ];
    }

    /**
     * True when height is 251 cm or more (max allowed height is 250 cm).
     */
    public function exceedsMaxAllowedHeight(?string $dimensionsCm): bool
    {
        $dimensions = $this->parseDimensionsCm($dimensionsCm);
        if ($dimensions === null) {
            return false;
        }

        return $dimensions['height'] > self::MAX_CARGO_HEIGHT_CM + self::EPSILON;
    }

    /**
     * True when L, W or H exceeds the standard pallet envelope by >= 0.01 cm.
     */
    public function isOversized(?string $dimensionsCm): bool
    {
        $dimensions = $this->parseDimensionsCm($dimensionsCm);
        if ($dimensions === null) {
            return false;
        }

        return $this->exceedsLimit($dimensions['length'], self::PALLET_LENGTH_CM)
            || $this->exceedsLimit($dimensions['width'], self::PALLET_WIDTH_CM)
            || $this->exceedsLimit($dimensions['height'], self::PALLET_HEIGHT_CM);
    }

    /**
     * Pallet places for a single oversized unit from footprint area (L × W):
     *   area <= 9600 cm²           → 1
     *   9600 < area <= 2·9600      → 2
     *   etc. (always rounded up).
     */
    public function palletPlacesForUnit(?string $dimensionsCm): int
    {
        $dimensions = $this->parseDimensionsCm($dimensionsCm);
        if ($dimensions === null) {
            return 1;
        }

        $area = $dimensions['length'] * $dimensions['width'];
        $places = (int) ceil($area / self::PALLET_AREA_CM2 - self::EPSILON);

        return max(1, $places);
    }

    private function exceedsLimit(float $valueCm, float $limitCm): bool
    {
        return $valueCm - $limitCm >= self::DIMENSION_TOLERANCE_CM - self::EPSILON;
    }
}

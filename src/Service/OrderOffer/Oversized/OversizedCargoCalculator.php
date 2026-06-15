<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Oversized;

/**
 * Pure geometry helper for oversized cargo.
 *
 * Standard pallet envelope (meters): 1.20 (L) x 0.80 (W) x 1.88 (H) = 1.8048 m³.
 * Dimensions are entered in centimeters on the order, so everything is
 * converted to meters here.
 *
 * "Oversized" is decided rotation-agnostically: the three cargo dimensions are
 * sorted and compared against the sorted envelope, so swapping/rotating sides
 * never changes the verdict. A side counts as exceeding only when it is at
 * least 0.01 m (1 cm) over its limit.
 */
final class OversizedCargoCalculator
{
    public const float PALLET_VOLUME_M3 = 1.8048;

    /** Sorted (descending) envelope limits in meters: 1.88, 1.20, 0.80. */
    private const array SORTED_MAX_DIMENSIONS_M = [1.88, 1.20, 0.80];

    /** A side must exceed its limit by at least this (meters) to count. */
    private const float DIMENSION_TOLERANCE_M = 0.01;

    /** Guards floating-point comparisons around the volume thresholds. */
    private const float EPSILON = 1.0e-9;

    /**
     * Parse a "WxLxH" (or "W,L,H") centimeter string into meters.
     *
     * @return array{0: float, 1: float, 2: float}|null three sides in meters, or null when unparseable
     */
    public function parseDimensionsMeters(?string $dimensionsCm): ?array
    {
        if ($dimensionsCm === null || trim($dimensionsCm) === '') {
            return null;
        }

        $parts = preg_split('/[x,Х×*]/iu', trim($dimensionsCm)) ?: [];
        if (count($parts) !== 3) {
            return null;
        }

        $meters = [];
        foreach ($parts as $part) {
            $normalized = str_replace(',', '.', trim($part));
            if (!is_numeric($normalized)) {
                return null;
            }
            $cm = (float) $normalized;
            if ($cm <= 0.0) {
                return null;
            }
            $meters[] = $cm / 100.0;
        }

        return [$meters[0], $meters[1], $meters[2]];
    }

    /**
     * True when at least one side exceeds the pallet envelope by >= 0.01 m,
     * regardless of orientation.
     */
    public function isOversized(?string $dimensionsCm): bool
    {
        $dimensions = $this->parseDimensionsMeters($dimensionsCm);
        if ($dimensions === null) {
            return false;
        }

        rsort($dimensions);

        foreach ($dimensions as $i => $side) {
            $limit = self::SORTED_MAX_DIMENSIONS_M[$i];
            if ($side - $limit >= self::DIMENSION_TOLERANCE_M - self::EPSILON) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pallet places occupied by a single oversized unit, based on its volume:
     *   V <= 1.8048            → 1
     *   1.8048 < V <= 2·1.8048 → 2
     *   2·1.8048 < V <= 3·…    → 3, etc.
     *
     * Returns at least 1. The caller decides whether the unit is oversized;
     * for non-oversized cargo a flat 1 place per unit should be used instead.
     */
    public function palletPlacesForUnit(?string $dimensionsCm): int
    {
        $dimensions = $this->parseDimensionsMeters($dimensionsCm);
        if ($dimensions === null) {
            return 1;
        }

        $volume = $dimensions[0] * $dimensions[1] * $dimensions[2];
        $places = (int) ceil($volume / self::PALLET_VOLUME_M3 - self::EPSILON);

        return max(1, $places);
    }
}

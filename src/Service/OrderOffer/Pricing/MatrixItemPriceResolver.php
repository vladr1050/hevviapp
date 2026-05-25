<?php

declare(strict_types=1);

namespace App\Service\OrderOffer\Pricing;

use App\Entity\MatrixItem;
use App\Entity\ServiceArea;

/**
 * Resolves matrix row price for total weight (half-open interval: weightFrom <= w < weightTo).
 */
final class MatrixItemPriceResolver
{
    public function resolvePriceCents(ServiceArea $serviceArea, int $totalWeightKg): ?int
    {
        $item = $this->findMatrixItemByWeight($serviceArea, $totalWeightKg);

        return $item?->getPrice();
    }

    public function findMatrixItemByWeight(ServiceArea $serviceArea, int $totalWeightKg): ?MatrixItem
    {
        $items = $serviceArea->getMatrixItems()->toArray();
        usort($items, static fn (MatrixItem $a, MatrixItem $b): int => ($a->getWeightFrom() ?? 0) <=> ($b->getWeightFrom() ?? 0));

        foreach ($items as $matrixItem) {
            $weightFrom = $matrixItem->getWeightFrom() ?? 0;
            $weightTo = $matrixItem->getWeightTo() ?? PHP_INT_MAX;

            if ($totalWeightKg >= $weightFrom && $totalWeightKg < $weightTo) {
                return $matrixItem;
            }
        }

        return null;
    }
}

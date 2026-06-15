<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OversizedWeightTierRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Maps a number of occupied pallet places to a fixed chargeable weight (kg).
 *
 * Used ONLY for delivery price calculation of oversized cargo. The weight a
 * sender enters on the order stays the source of truth for all documents;
 * this fixed weight never leaves the pricing pipeline.
 */
#[ORM\Entity(repositoryClass: OversizedWeightTierRepository::class)]
#[ORM\Table(name: 'oversized_weight_tier')]
#[ORM\UniqueConstraint(name: 'uniq_oversized_weight_tier_pallets', columns: ['pallets'])]
class OversizedWeightTier extends BaseUUID
{
    /** Number of pallet places this tier maps to (>= 1). */
    #[ORM\Column]
    private int $pallets = 1;

    /** Fixed chargeable weight (kg) for the given number of pallet places. */
    #[ORM\Column]
    private int $weightKg = 0;

    public function getPallets(): int
    {
        return $this->pallets;
    }

    public function setPallets(int $pallets): static
    {
        $this->pallets = $pallets;

        return $this;
    }

    public function getWeightKg(): int
    {
        return $this->weightKg;
    }

    public function setWeightKg(int $weightKg): static
    {
        $this->weightKg = $weightKg;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%d pallet(s) → %d kg', $this->pallets, $this->weightKg);
    }
}

<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Per-carrier freight pricing strategy.
 */
enum PricingAlgorithm: string
{
    /** Price from drop-off zone matrix by total weight (legacy). */
    case FLAT_BY_DROP_OFF_ZONE = 'flat_by_drop_off_zone';

    /** Hub-and-spoke: prices relative to country home zone (e.g. Riga for LV). */
    case HUB_AND_SPOKE = 'hub_and_spoke';

    public function labelKey(): string
    {
        return match ($this) {
            self::FLAT_BY_DROP_OFF_ZONE => 'pricing_algorithm.flat_by_drop_off_zone',
            self::HUB_AND_SPOKE => 'pricing_algorithm.hub_and_spoke',
        };
    }

    /**
     * @return array<string, self>
     */
    public static function choices(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->labelKey()] = $case;
        }

        return $out;
    }
}

<?php

declare(strict_types=1);

namespace App\Enum;

enum OfferPriceAdjustmentMode: string
{
    case TARGET_TOTAL = 'target_total';
    case PERCENT = 'percent';
    case DELTA = 'delta';
}

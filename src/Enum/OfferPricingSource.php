<?php

declare(strict_types=1);

namespace App\Enum;

enum OfferPricingSource: string
{
    case CALCULATED = 'calculated';
    case MANUAL = 'manual';
}

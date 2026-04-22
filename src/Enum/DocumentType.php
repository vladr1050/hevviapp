<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Order-linked PDF document kinds (single Twig pipeline, different blocks per type).
 */
enum DocumentType: string
{
    case PAYMENT_NOTICE = 'PAYMENT_NOTICE';

    case CUSTOMER_INVOICE = 'CUSTOMER_INVOICE';

    case CARRIER_INVOICE = 'CARRIER_INVOICE';
}

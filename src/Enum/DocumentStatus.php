<?php

declare(strict_types=1);

namespace App\Enum;

enum DocumentStatus: string
{
    case GENERATED = 'GENERATED';

    case SENT = 'SENT';

    case FAILED = 'FAILED';
}

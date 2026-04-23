<?php

declare(strict_types=1);

namespace App\Enum;

enum TermsAudience: string
{
    case Carrier = 'carrier';
    case Sender = 'sender';
}

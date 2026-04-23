<?php

declare(strict_types=1);

namespace App\Enum;

enum TermsRevisionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Superseded = 'superseded';
}

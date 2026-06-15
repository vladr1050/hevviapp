<?php

declare(strict_types=1);

namespace App\Enum;

enum WaitingListApplicantType: string
{
    case Sender = 'sender';
    case Carrier = 'carrier';

    public function labelLv(): string
    {
        return match ($this) {
            self::Sender => 'Sūtītājs',
            self::Carrier => 'Pārvadātājs',
        };
    }

    public static function tryFromRequest(string $value): ?self
    {
        $normalized = strtolower(trim($value));

        return self::tryFrom($normalized);
    }
}

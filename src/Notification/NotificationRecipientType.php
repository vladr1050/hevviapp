<?php

declare(strict_types=1);

namespace App\Notification;

final class NotificationRecipientType
{
    public const SENDER = 'sender';

    public const CARRIER = 'carrier';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::SENDER, self::CARRIER];
    }
}

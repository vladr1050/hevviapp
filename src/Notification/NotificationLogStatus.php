<?php

declare(strict_types=1);

namespace App\Notification;

final class NotificationLogStatus
{
    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PENDING, self::SENT, self::FAILED];
    }
}

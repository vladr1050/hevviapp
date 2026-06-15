<?php

declare(strict_types=1);

namespace App\Notification;

final class NotificationRecipientType
{
    public const SENDER = 'sender';

    public const CARRIER = 'carrier';

    /** Waiting list applicant (email entered on the public form). */
    public const APPLICANT = 'applicant';

    /** Invoice-issuing billing company (operator). */
    public const OPERATOR = 'operator';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::SENDER, self::CARRIER, self::APPLICANT, self::OPERATOR];
    }
}

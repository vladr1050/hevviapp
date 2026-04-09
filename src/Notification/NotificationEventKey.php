<?php

declare(strict_types=1);

namespace App\Notification;

/**
 * Normalized business event keys for notification rules (DB + triggers).
 */
final class NotificationEventKey
{
    public const ORDER_PRICE_CONFIRMED = 'ORDER_PRICE_CONFIRMED';

    public const ORDER_ASSIGNED_TO_CARRIER = 'ORDER_ASSIGNED_TO_CARRIER';

    public const ORDER_STATUS_CHANGED_TO_ACCEPTED = 'ORDER_STATUS_CHANGED_TO_ACCEPTED';

    public const ORDER_STATUS_CHANGED_TO_ASSIGNED = 'ORDER_STATUS_CHANGED_TO_ASSIGNED';

    public const ORDER_STATUS_CHANGED_TO_IN_TRANSIT = 'ORDER_STATUS_CHANGED_TO_IN_TRANSIT';

    public const ORDER_STATUS_CHANGED_TO_DELIVERED = 'ORDER_STATUS_CHANGED_TO_DELIVERED';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::ORDER_PRICE_CONFIRMED,
            self::ORDER_ASSIGNED_TO_CARRIER,
            self::ORDER_STATUS_CHANGED_TO_ACCEPTED,
            self::ORDER_STATUS_CHANGED_TO_ASSIGNED,
            self::ORDER_STATUS_CHANGED_TO_IN_TRANSIT,
            self::ORDER_STATUS_CHANGED_TO_DELIVERED,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Message\Notification;

/**
 * Async notification request (order status / assignment). Invoice emails stay synchronous in {@see \App\Service\Notification\NotificationDispatchService}.
 */
final readonly class NotificationEventMessage
{
    public function __construct(
        public string $orderId,
        public string $eventKey,
        public ?string $invoiceId = null,
        public bool $forceResend = false,
    ) {
    }
}

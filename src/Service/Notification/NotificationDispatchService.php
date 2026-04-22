<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Document;
use App\Entity\Invoice;
use App\Entity\Order;
use App\Message\Notification\NotificationEventMessage;
use App\Notification\NotificationEventKey;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Entry point: invoice email stays synchronous; other events go through Messenger (transport DSN may be sync:// or doctrine://).
 */
final class NotificationDispatchService
{
    public function __construct(
        private readonly NotificationRuleProcessor $notificationRuleProcessor,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function dispatch(
        Order $order,
        string $eventKey,
        ?Invoice $invoice = null,
        bool $forceResend = false,
        ?Document $document = null,
    ): NotificationDispatchResult {
        if ($eventKey === NotificationEventKey::ORDER_PRICE_CONFIRMED
            || $eventKey === NotificationEventKey::ORDER_DELIVERED_SENDER_DOCUMENT
            || $eventKey === NotificationEventKey::ORDER_DELIVERED_CARRIER_DOCUMENT) {
            return $this->notificationRuleProcessor->process($order, $eventKey, $invoice, $forceResend, $document);
        }

        $orderId = $order->getId();
        if ($orderId === null) {
            return new NotificationDispatchResult(0);
        }

        $this->bus->dispatch(new NotificationEventMessage(
            $orderId->toRfc4122(),
            $eventKey,
            $invoice?->getId()?->toRfc4122(),
            $forceResend,
        ));

        return new NotificationDispatchResult(0);
    }
}

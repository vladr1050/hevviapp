<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Message\Notification\NotificationEventMessage;
use App\Repository\InvoiceRepository;
use App\Repository\OrderRepository;
use App\Service\Notification\NotificationRuleProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class NotificationEventHandler
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly NotificationRuleProcessor $notificationRuleProcessor,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(NotificationEventMessage $message): void
    {
        try {
            $orderId = Uuid::fromString($message->orderId);
        } catch (\InvalidArgumentException) {
            $this->logger->warning('NotificationEventMessage: invalid order UUID', [
                'order_id' => $message->orderId,
            ]);

            return;
        }

        $order = $this->orderRepository->find($orderId);
        if ($order === null) {
            $this->logger->warning('NotificationEventMessage: order not found', [
                'order_id' => $message->orderId,
            ]);

            return;
        }

        $invoice = null;
        if ($message->invoiceId !== null && $message->invoiceId !== '') {
            try {
                $invId = Uuid::fromString($message->invoiceId);
                $invoice = $this->invoiceRepository->find($invId);
            } catch (\InvalidArgumentException) {
                $this->logger->warning('NotificationEventMessage: invalid invoice UUID', [
                    'invoice_id' => $message->invoiceId,
                ]);
            }
        }

        $this->notificationRuleProcessor->process(
            $order,
            $message->eventKey,
            $invoice,
            $message->forceResend,
        );
    }
}

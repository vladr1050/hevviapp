<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains
 * the property of SIA SLYFOX, its suppliers and Customers,
 * if any.  The intellectual and technical concepts contained
 * herein are proprietary to SIA SLYFOX
 * its Suppliers and Customers are protected by trade secret or copyright law.
 *
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained.
 */

namespace App\Service\OrderStatus;

use App\Entity\Order;
use App\Service\Email\Contract\EmailServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Сервис для управления статусами заказов и отправки уведомлений
 * Следует Single Responsibility Principle (SOLID)
 */
class OrderStatusService
{
    /**
     * Статусы для которых отправляются email уведомления
     */
    private const array EMAIL_NOTIFICATION_STATUSES = [
        Order::STATUS['ACCEPTED'],
        Order::STATUS['ASSIGNED'],
        Order::STATUS['PICKUP_DONE'],
        Order::STATUS['DELIVERED'],
    ];

    /**
     * Маппинг статусов на шаблоны email
     */
    private const array STATUS_TEMPLATE_MAP = [
        Order::STATUS['ACCEPTED'] => 'email/order_status/accepted.html.twig',
        Order::STATUS['ASSIGNED'] => 'email/order_status/assigned.html.twig',
        Order::STATUS['PICKUP_DONE'] => 'email/order_status/pickup_done.html.twig',
        Order::STATUS['DELIVERED'] => 'email/order_status/delivered.html.twig',
    ];

    /**
     * Маппинг статусов на ключи переводов для темы письма
     */
    private const array STATUS_SUBJECT_MAP = [
        Order::STATUS['ACCEPTED'] => 'email.order_status.accepted.title',
        Order::STATUS['ASSIGNED'] => 'email.order_status.assigned.title',
        Order::STATUS['PICKUP_DONE'] => 'email.order_status.pickup_done.title',
        Order::STATUS['DELIVERED'] => 'email.order_status.delivered.title',
    ];

    public function __construct(
        private readonly EmailServiceInterface $emailService,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Отправка email уведомления отправителю заказа
     */
    public function sendEmailToSender(Order $order): void
    {
        $sender = $order->getSender();
        if (!$sender) {
            $this->logger->warning('Cannot send email: order has no sender', [
                'order_id' => $order->getId()?->toRfc4122(),
            ]);
            return;
        }

        $status = $order->getStatus();
        if (!$this->shouldSendEmailForStatus($status)) {
            $this->logger->debug('Email notification not configured for this status', [
                'order_id' => $order->getId()?->toRfc4122(),
                'status' => $status,
            ]);
            return;
        }

        try {
            $locale = $sender->getLocale() ?? 'en';
            $template = $this->getTemplateForStatus($status);
            $subject = $this->getSubjectForStatus($status, $locale);

            $htmlContent = $this->twig->render($template, [
                'order' => $order,
                'sender' => $sender,
                'locale' => $locale,
            ]);


            $success = $this->emailService->send(
                to: $sender->getEmail(),
                subject: $subject,
                htmlContent: $htmlContent
            );

            if ($success) {
                $this->logger->info('Order status email sent successfully', [
                    'order_id' => $order->getId()?->toRfc4122(),
                    'status' => $status,
                    'recipient' => $sender->getEmail(),
                    'locale' => $locale,
                ]);
            } else {
                $this->logger->error('Failed to send order status email', [
                    'order_id' => $order->getId()?->toRfc4122(),
                    'status' => $status,
                    'recipient' => $sender->getEmail(),
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Exception while sending order status email', [
                'order_id' => $order->getId()?->toRfc4122(),
                'status' => $status,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Проверяет, нужно ли отправлять email для данного статуса
     */
    private function shouldSendEmailForStatus(?int $status): bool
    {
        return $status !== null && in_array($status, self::EMAIL_NOTIFICATION_STATUSES, true);
    }

    /**
     * Получает шаблон для статуса
     */
    private function getTemplateForStatus(int $status): string
    {
        if (!isset(self::STATUS_TEMPLATE_MAP[$status])) {
            throw new \InvalidArgumentException("No template configured for status: {$status}");
        }

        return self::STATUS_TEMPLATE_MAP[$status];
    }

    /**
     * Получает тему письма для статуса с учетом локализации
     */
    private function getSubjectForStatus(int $status, string $locale): string
    {
        if (!isset(self::STATUS_SUBJECT_MAP[$status])) {
            throw new \InvalidArgumentException("No subject configured for status: {$status}");
        }

        return $this->translator->trans(
            self::STATUS_SUBJECT_MAP[$status],
            [],
            'AppBundle',
            $locale
        );
    }
}

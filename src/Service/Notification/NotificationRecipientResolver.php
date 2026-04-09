<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\NotificationRule;
use App\Entity\Order;
use App\Notification\NotificationRecipientType;

final class NotificationRecipientResolver
{
    public function resolveEmail(NotificationRule $rule, Order $order): ?string
    {
        $type = $rule->getRecipientType();
        if ($type === NotificationRecipientType::SENDER) {
            $sender = $order->getSender();
            if ($sender === null) {
                return null;
            }
            $email = trim((string) ($sender->getEmail() ?? ''));

            return $email !== '' ? $email : null;
        }
        if ($type === NotificationRecipientType::CARRIER) {
            $carrier = $order->getCarrier();
            if ($carrier === null) {
                return null;
            }
            $email = trim((string) ($carrier->getEmail() ?? ''));

            return $email !== '' ? $email : null;
        }

        return null;
    }
}

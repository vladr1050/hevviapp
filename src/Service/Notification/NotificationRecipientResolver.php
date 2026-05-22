<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Carrier;
use App\Entity\NotificationRule;
use App\Entity\Order;
use App\Entity\OrderAssignment;
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
            $carrier = $this->resolveCarrier($order);
            if ($carrier === null) {
                return null;
            }
            $email = trim((string) ($carrier->getEmail() ?? ''));

            return $email !== '' ? $email : null;
        }

        return null;
    }

    /**
     * Order.carrier ставится только когда assignment подтверждён (ACCEPTED).
     * До этого ASSIGNED-уведомление о новом запросе должно идти в активного
     * (не REJECTED) OrderAssignment перевозчика.
     */
    private function resolveCarrier(Order $order): ?Carrier
    {
        $carrier = $order->getCarrier();
        if ($carrier instanceof Carrier) {
            return $carrier;
        }

        foreach ($order->getOrderAssignments() as $assignment) {
            if ($assignment->getStatus() === OrderAssignment::STATUS['REJECTED']) {
                continue;
            }
            $assignmentCarrier = $assignment->getCarrier();
            if ($assignmentCarrier instanceof Carrier) {
                return $assignmentCarrier;
            }
        }

        return null;
    }
}

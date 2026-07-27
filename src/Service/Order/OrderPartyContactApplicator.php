<?php

declare(strict_types=1);

namespace App\Service\Order;

use App\Entity\Order;
use Symfony\Component\HttpFoundation\Request;

/**
 * Applies optional shipper / consignee contact fields from portal forms.
 */
final class OrderPartyContactApplicator
{
    public function applyFromRequest(Order $order, Request $request): void
    {
        $order->setShipperCompanyName($this->nullableString($request->request->get('shipper_company_name')));
        $order->setShipperPhone($this->nullableString($request->request->get('shipper_phone')));
        $order->setShipperContactName($this->nullableString($request->request->get('shipper_contact_name')));

        if ($request->request->getBoolean('consignee_same_as_shipper')) {
            $order->setConsigneeCompanyName($order->getShipperCompanyName());
            $order->setConsigneePhone($order->getShipperPhone());
            $order->setConsigneeContactName($order->getShipperContactName());

            return;
        }

        $order->setConsigneeCompanyName($this->nullableString($request->request->get('consignee_company_name')));
        $order->setConsigneePhone($this->nullableString($request->request->get('consignee_phone')));
        $order->setConsigneeContactName($this->nullableString($request->request->get('consignee_contact_name')));
    }

    /**
     * @return array{
     *     shipper_company_name: ?string,
     *     shipper_phone: ?string,
     *     shipper_contact_name: ?string,
     *     consignee_company_name: ?string,
     *     consignee_phone: ?string,
     *     consignee_contact_name: ?string
     * }
     */
    public function serialize(Order $order): array
    {
        return [
            'shipper_company_name' => $order->getShipperCompanyName(),
            'shipper_phone' => $order->getShipperPhone(),
            'shipper_contact_name' => $order->getShipperContactName(),
            'consignee_company_name' => $order->getConsigneeCompanyName(),
            'consignee_phone' => $order->getConsigneePhone(),
            'consignee_contact_name' => $order->getConsigneeContactName(),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}

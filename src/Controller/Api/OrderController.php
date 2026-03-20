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

namespace App\Controller\Api;

use App\Entity\Cargo;
use App\Entity\Order;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/orders', name: 'api_order_')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Создаёт новый заказ (Order + один или несколько Cargo) от имени авторизованного пользователя.
     *
     * Expected JSON body:
     * {
     *   "pickupAddress":    string (required),
     *   "dropoutAddress":   string (required),
     *   "pickupLatitude":   float|null,
     *   "pickupLongitude":  float|null,
     *   "dropoutLatitude":  float|null,
     *   "dropoutLongitude": float|null,
     *   "notes":            string|null,
     *   "pickupTimeFrom":   string|null  (H:i),
     *   "pickupTimeTo":     string|null  (H:i),
     *   "pickupDate":       string|null  (Y-m-d),
     *   "deliveryDate":     string|null  (Y-m-d),
     *   "cargo": [
     *     {
     *       "type":              int (1=PALLET, 2=OVERSIZED),
     *       "quantity":          int  (required),
     *       "weightKg":          int  (required),
     *       "dimensionsCm":      string|null (e.g. "120x80x50"),
     *       "name":              string (required),
     *       "stackable":         bool,
     *       "manipulatorNeeded": bool
     *     },
     *     ...
     *   ]
     * }
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], JsonResponse::HTTP_BAD_REQUEST);
        }

        foreach (['pickupAddress', 'dropoutAddress', 'cargo'] as $field) {
            if (empty($data[$field])) {
                return $this->json(
                    ['error' => sprintf('Field "%s" is required', $field)],
                    JsonResponse::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        $cargoList = $data['cargo'];
        if (!is_array($cargoList) || !array_is_list($cargoList) || empty($cargoList)) {
            return $this->json(
                ['error' => 'Field "cargo" must be a non-empty array of cargo items'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        foreach ($cargoList as $index => $cargoData) {
            foreach (['quantity', 'weightKg'] as $field) {
                if (!isset($cargoData[$field]) || $cargoData[$field] === '' || $cargoData[$field] === null) {
                    return $this->json(
                        ['error' => sprintf('Field "cargo[%d].%s" is required', $index, $field)],
                        JsonResponse::HTTP_UNPROCESSABLE_ENTITY
                    );
                }
            }
        }

        // Build Order
        $order = new Order();
        $order->setSender($user);
        $order->setStatus(Order::STATUS['DRAFT']);
        $order->setPickupAddress((string) $data['pickupAddress']);
        $order->setDropoutAddress((string) $data['dropoutAddress']);

        if (!empty($data['pickupLatitude'])) {
            $order->setPickupLatitude((string) $data['pickupLatitude']);
        }
        if (!empty($data['pickupLongitude'])) {
            $order->setPickupLongitude((string) $data['pickupLongitude']);
        }
        if (!empty($data['dropoutLatitude'])) {
            $order->setDropoutLatitude((string) $data['dropoutLatitude']);
        }
        if (!empty($data['dropoutLongitude'])) {
            $order->setDropoutLongitude((string) $data['dropoutLongitude']);
        }
        if (!empty($data['notes'])) {
            $order->setNotes((string) $data['notes']);
        }
        if (!empty($data['pickupTimeFrom'])) {
            $order->setPickupTimeFrom(\DateTime::createFromFormat('H:i', $data['pickupTimeFrom']) ?: null);
        }
        if (!empty($data['pickupTimeTo'])) {
            $order->setPickupTimeTo(\DateTime::createFromFormat('H:i', $data['pickupTimeTo']) ?: null);
        }
        $pickupDate = !empty($data['pickupDate'])
            ? (\DateTime::createFromFormat('Y-m-d', $data['pickupDate']) ?: new \DateTime('today'))
            : new \DateTime('today');
        $order->setPickupDate($pickupDate);
        if (!empty($data['deliveryDate'])) {
            $order->setDeliveryDate(\DateTime::createFromFormat('Y-m-d', $data['deliveryDate']) ?: null);
        }

        // Build each Cargo and attach via addCargo() so the in-memory collection
        // is populated before postPersist fires (the offer calculator iterates
        // $order->getCargo() to compute total weight across all cargo items).
        foreach ($cargoList as $cargoData) {
            $cargo = $this->buildCargoFromData($cargoData);
            $order->addCargo($cargo);
            $this->em->persist($cargo);
        }

        $this->em->persist($order);
        $this->em->flush();

        return $this->json(
            ['id' => $order->getId()?->toRfc4122()],
            JsonResponse::HTTP_CREATED
        );
    }

    /**
     * Создаёт объект Cargo из сырых данных запроса.
     *
     * @param array<string, mixed> $cargoData
     */
    private function buildCargoFromData(array $cargoData): Cargo
    {
        $cargo = new Cargo();

        $cargoType = isset($cargoData['type']) ? (int) $cargoData['type'] : Cargo::TYPE['PALLET'];
        $cargo->setType(in_array($cargoType, Cargo::TYPE, true) ? $cargoType : Cargo::TYPE['PALLET']);
        $cargo->setQuantity((int) $cargoData['quantity']);
        $cargo->setWeightKg((int) $cargoData['weightKg']);
        $cargo->setName(!empty($cargoData['name']) ? (string) $cargoData['name'] : $cargo->getTypeLabel());
        $cargo->setStackable((bool) ($cargoData['stackable'] ?? false));
        $cargo->setManipulatorNeeded((bool) ($cargoData['manipulatorNeeded'] ?? false));

        if (!empty($cargoData['dimensionsCm'])) {
            $cargo->setDimensionsCm((string) $cargoData['dimensionsCm']);
        }

        return $cargo;
    }
}

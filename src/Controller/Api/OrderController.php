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
use App\Entity\OrderAttachment;
use App\Entity\User;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Repository\OrderRepository;
use App\Service\OrderAttachmentUploader;
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
        private readonly EntityManagerInterface  $em,
        private readonly OrderRepository         $orderRepository,
        private readonly OrderAttachmentUploader $attachmentUploader,
        private readonly TranslatorInterface     $translator,
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
     *   "stackable":         bool,
     *   "manipulatorNeeded": bool,
     *   "cargo": [
     *     {
     *       "type":         int (1=PALLET, 2=OVERSIZED),
     *       "quantity":     int    (required),
     *       "weightKg":     int    (required),
     *       "dimensionsCm": string|null (e.g. "120x80x50"),
     *       "name":         string (required)
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
        $order->setStackable((bool) ($data['stackable'] ?? false));
        $order->setManipulatorNeeded((bool) ($data['manipulatorNeeded'] ?? false));

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
            [
                'id'        => $order->getId()?->toRfc4122(),
                'reference' => $order->getReference(),
            ],
            JsonResponse::HTTP_CREATED
        );
    }

    /**
     * Обновляет заказ в статусе DRAFT.
     *
     * Разрешено только владельцу заказа. Заказ обязан быть в статусе DRAFT —
     * только на этом этапе у него нет оффера и правки имеют смысл.
     *
     * После flush автоматически срабатывает OrderOfferAutoCreateListener::postUpdate,
     * который пересчитывает и создаёт оффер при наличии координат.
     *
     * Expected JSON body (все поля опциональны кроме cargo и keepAttachments):
     * {
     *   "pickupAddress":    string,
     *   "dropoutAddress":   string,
     *   "pickupLatitude":   float|null,
     *   "pickupLongitude":  float|null,
     *   "dropoutLatitude":  float|null,
     *   "dropoutLongitude": float|null,
     *   "notes":            string|null,
     *   "pickupTimeFrom":   string|null  (H:i),
     *   "pickupTimeTo":     string|null  (H:i),
     *   "pickupDate":       string|null  (Y-m-d),
     *   "stackable":         bool,
     *   "manipulatorNeeded": bool,
     *   "cargo": [ { "type": int, "quantity": int, "weightKg": int, "dimensionsCm": string|null, "name": string }, ... ],
     *   "keepAttachments":  string[]  (salts существующих вложений, которые нужно сохранить)
     * }
     */
    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user  = $this->getUser();
        $order = $this->orderRepository->find($id);

        if (!$order || $order->getSender() !== $user) {
            return $this->json(['error' => 'Order not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($order->getStatus() !== Order::STATUS['DRAFT']) {
            return $this->json(
                ['error' => 'Only DRAFT orders can be edited.'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        foreach (['pickupAddress', 'dropoutAddress', 'cargo'] as $field) {
            if (empty($data[$field])) {
                return $this->json(
                    ['error' => sprintf('Field "%s" is required.', $field)],
                    JsonResponse::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        $cargoList = $data['cargo'];
        if (!is_array($cargoList) || !array_is_list($cargoList) || empty($cargoList)) {
            return $this->json(
                ['error' => 'Field "cargo" must be a non-empty array.'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        foreach ($cargoList as $index => $cargoData) {
            foreach (['quantity', 'weightKg'] as $field) {
                if (!isset($cargoData[$field]) || $cargoData[$field] === '' || $cargoData[$field] === null) {
                    return $this->json(
                        ['error' => sprintf('Field "cargo[%d].%s" is required.', $index, $field)],
                        JsonResponse::HTTP_UNPROCESSABLE_ENTITY
                    );
                }
            }
        }

        // Обновляем поля заказа
        $this->applyOrderFields($order, $data);

        // Заменяем коллекцию груза целиком
        foreach ($order->getCargo()->toArray() as $existingCargo) {
            $order->removeCargo($existingCargo);
            $this->em->remove($existingCargo);
        }

        foreach ($cargoList as $cargoData) {
            $cargo = $this->buildCargoFromData($cargoData);
            $order->addCargo($cargo);
            $this->em->persist($cargo);
        }

        // Управление вложениями — только если поле явно передано в теле запроса.
        // PATCH-семантика: отсутствие поля = «не трогать вложения».
        //
        // Сценарии:
        //   keepAttachments: ['salt1']   → salt1 остаётся, остальные удаляются
        //   keepAttachments: []          → все существующие вложения удаляются
        //   поле не передано             → вложения не изменяются (безопасный дефолт)
        if (array_key_exists('keepAttachments', $data)) {
            $keepSalts = array_values(array_filter(
                array_map('strval', (array) $data['keepAttachments']),
                static fn(string $s): bool => $s !== ''
            ));

            foreach ($order->getAttachments()->toArray() as $attachment) {
                /** @var OrderAttachment $attachment */
                if (!in_array($attachment->getSalt(), $keepSalts, true)) {
                    $this->attachmentUploader->delete($attachment);
                }
            }
        }

        // flush → OrderOfferAutoCreateListener::postUpdate попробует создать оффер
        $this->em->flush();

        return $this->json(
            ['id' => $order->getId()?->toRfc4122()],
            JsonResponse::HTTP_OK
        );
    }

    /**
     * Аннулирует текущую котировку (OFFERED → DRAFT, офферы удаляются).
     * Следующий flush не создаёт оффер автоматически; новый расчёт — после PATCH с данными заказа.
     */
    #[Route('/{id}/void-quote', name: 'void_quote', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function voidQuote(string $id): JsonResponse
    {
        /** @var User $user */
        $user  = $this->getUser();
        $order = $this->orderRepository->find($id);

        if (!$order || $order->getSender() !== $user) {
            return $this->json(['error' => 'Order not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($order->getStatus() !== Order::STATUS['OFFERED']) {
            return $this->json(
                ['error' => 'Quote can only be voided for orders awaiting confirmation (OFFERED).'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        foreach ($order->getOffers()->toArray() as $offer) {
            $order->removeOffer($offer);
            $this->em->remove($offer);
        }

        $order->setStatus(Order::STATUS['DRAFT']);
        $order->setSkipNextOfferAutoCreate(true);
        $this->em->flush();

        $locale = $user->getLocale() ?? 'en';

        return $this->json([
            'id'           => $order->getId()?->toRfc4122(),
            'status'       => $order->getStatus(),
            'status_text'  => $this->translator->trans(
                'order.status_' . $order->getStatus(),
                [],
                'AppBundle',
                $locale
            ),
            'price'    => null,
            'vat'      => null,
            'brutto'   => null,
            'fee'      => null,
            'subtotal' => null,
        ]);
    }

    /**
     * Применяет скалярные поля из тела запроса к сущности Order.
     *
     * @param array<string, mixed> $data
     */
    private function applyOrderFields(Order $order, array $data): void
    {
        $order->setPickupAddress((string) $data['pickupAddress']);
        $order->setDropoutAddress((string) $data['dropoutAddress']);

        $order->setPickupLatitude(!empty($data['pickupLatitude']) ? (string) $data['pickupLatitude'] : null);
        $order->setPickupLongitude(!empty($data['pickupLongitude']) ? (string) $data['pickupLongitude'] : null);
        $order->setDropoutLatitude(!empty($data['dropoutLatitude']) ? (string) $data['dropoutLatitude'] : null);
        $order->setDropoutLongitude(!empty($data['dropoutLongitude']) ? (string) $data['dropoutLongitude'] : null);

        $order->setNotes(!empty($data['notes']) ? (string) $data['notes'] : null);
        $order->setStackable((bool) ($data['stackable'] ?? false));
        $order->setManipulatorNeeded((bool) ($data['manipulatorNeeded'] ?? false));

        $order->setPickupTimeFrom(
            !empty($data['pickupTimeFrom'])
                ? (\DateTime::createFromFormat('H:i', $data['pickupTimeFrom']) ?: null)
                : null
        );
        $order->setPickupTimeTo(
            !empty($data['pickupTimeTo'])
                ? (\DateTime::createFromFormat('H:i', $data['pickupTimeTo']) ?: null)
                : null
        );

        if (!empty($data['pickupDate'])) {
            $order->setPickupDate(\DateTime::createFromFormat('Y-m-d', $data['pickupDate']) ?: new \DateTime('today'));
        }

        if (array_key_exists('deliveryDate', $data)) {
            $order->setDeliveryDate(
                !empty($data['deliveryDate'])
                    ? (\DateTime::createFromFormat('Y-m-d', $data['deliveryDate']) ?: null)
                    : null
            );
        }
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

        if (!empty($cargoData['dimensionsCm'])) {
            $cargo->setDimensionsCm((string) $cargoData['dimensionsCm']);
        }

        return $cargo;
    }
}

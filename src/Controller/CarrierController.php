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

namespace App\Controller;

use App\Entity\Cargo;
use App\Entity\Carrier;
use App\Entity\Order;
use App\Entity\OrderAssignment;
use App\Entity\OrderAttachment;
use App\Entity\OrderHistory;
use App\Entity\OrderOffer;
use App\Repository\OrderAssignmentRepository;
use App\Repository\OrderRepository;
use App\Service\Order\SenderOrderPayableTotalCentsCalculator;
use App\Twig\Extension\Filter\MoneyExtension;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/carrier', name: 'carrier_')]
#[IsGranted('ROLE_CARRIER')]
class CarrierController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository           $orderRepository,
        private readonly OrderAssignmentRepository $orderAssignmentRepository,
        private readonly MoneyExtension            $moneyExtension,
        private readonly TranslatorInterface       $translator,
        private readonly EntityManagerInterface    $em,
        private readonly SenderOrderPayableTotalCentsCalculator $senderOrderPayableTotalCentsCalculator,
    )
    {
    }

    #[Route('/requests', name: 'public_requests')]
    public function requests(): Response
    {
        /** @var Carrier $user */
        $user = $this->getUser();

        $listOfOrders = [];
        foreach ($this->orderRepository->findRequestsByCarrier($user) as $order) {
            $history = $this->resolvePickupHistory($order);

            $listOfOrders[] = [
                'id' => $order->getId()?->toRfc4122(),
                'reference' => $order->getReference(),
                'status' => $order->getStatus(),
                'status_text' => $this->translator->trans('order.status_' . $order->getStatus(), domain: 'AppBundle', locale: $user->getLocale()),
                'price' => $this->moneyExtension->currencyConvert($this->resolveBaseFreight($order->getLatestOffer()), $order->getCurrency()),
                'vat' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getVat(), $order->getCurrency()),
                'brutto' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getBrutto(), $order->getCurrency()),
                'fee' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getFee(), $order->getCurrency()),
                'subtotal' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getNetto(), $order->getCurrency()),
                ...$this->buildCarrierFreightPriceBreakdown($order),
                'address' => [
                    'from' => $order->getPickupAddress(),
                    'to' => $order->getDropoutAddress(),
                ],
                'attachments' => $this->buildAttachmentList($order),
                'cargo' => $this->buildCargoList($order, $user->getLocale()),
                'stackable' => $order->isStackable(),
                'manipulator_needed' => $order->isManipulatorNeeded(),
                'comment' => $order->getNotes(),
                'pickup_date' => false !== $history ? $history->getCreatedAt()->format('d.m.Y') : null,
                'pickup_latitude' => $order->getPickupLatitude(),
                'pickup_longitude' => $order->getPickupLongitude(),
                'dropout_latitude' => $order->getDropoutLatitude(),
                'dropout_longitude' => $order->getDropoutLongitude(),
                'pickup_time_from' => $order->getPickupTimeFrom()?->format('H:i'),
                'pickup_time_to' => $order->getPickupTimeTo()?->format('H:i'),
                'delivery_time_from' => $order->getDeliveryTimeFrom()?->format('H:i'),
                'delivery_time_to' => $order->getDeliveryTimeTo()?->format('H:i'),
                'pickup_request_date' => $order->getPickupDate()?->format('d.m.Y'),
                'delivery_date' => $order->getDeliveryDate()?->format('d.m.Y'),
                'sender' => [
                    'first_name' => $order->getSender()->getFirstName(),
                    'last_name' => $order->getSender()->getLastName(),
                ],
            ];
        }

        return $this->render('public/carrier/pages/requests.html.twig', [
            'title' => $this->translator->trans('show.label_requests', domain: 'AppBundle', locale: $user->getLocale()),
            'user' => $this->buildCarrierContext($user),
            'orders' => $listOfOrders,
        ]);
    }

    #[Route('/requests/{id}/decline', name: 'public_request_decline', methods: ['POST'])]
    public function declineRequest(string $id): Response
    {
        /** @var Carrier $user */
        $user = $this->getUser();

        $order = $this->orderRepository->find($id);
        if (!$order) {
            return $this->redirectToRoute('carrier_public_requests');
        }

        $assignment = $this->orderAssignmentRepository->findAssignedByOrderAndCarrier($order, $user);
        if (!$assignment) {
            return $this->redirectToRoute('carrier_public_requests');
        }

        $assignment->setStatus(OrderAssignment::STATUS['REJECTED']);
        $order->setStatus(Order::STATUS['PAID']);

        $this->em->flush();

        return $this->redirectToRoute('carrier_public_requests');
    }

    #[Route('/requests/{id}/confirm', name: 'public_request_confirm', methods: ['POST'])]
    public function confirmRequest(string $id): Response
    {
        /** @var Carrier $user */
        $user = $this->getUser();

        $order = $this->orderRepository->find($id);
        if (!$order) {
            return $this->redirectToRoute('carrier_public_requests');
        }

        $assignment = $this->orderAssignmentRepository->findAssignedByOrderAndCarrier($order, $user);
        if (!$assignment) {
            return $this->redirectToRoute('carrier_public_requests');
        }

        $assignment->setStatus(OrderAssignment::STATUS['ACCEPTED']);
        $order->setStatus(Order::STATUS['AWAITING_PICKUP']);
        $order->setCarrier($user);

        $this->em->flush();

        return $this->redirectToRoute('carrier_public_order', ['id' => $id]);
    }

    #[Route('/orders/{id}/cancel', name: 'public_order_cancel', methods: ['POST'])]
    public function cancelOrder(string $id, Request $request, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        $token = new CsrfToken('cancel_order', (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var Carrier $user */
        $user = $this->getUser();

        $order = $this->orderRepository->find($id);
        if (!$order || $order->getCarrier() !== $user) {
            return $this->redirectToRoute('carrier_public_orders');
        }

        $order->setStatus(Order::STATUS['CANCELLED']);
        $order->setCancelReason($this->resolveCancelReason($request));

        $this->em->flush();

        return $this->redirectToRoute('carrier_public_orders');
    }

    /**
     * Собирает переведённую причину отмены из полей формы CancelModal.
     *
     * Radio-варианты для перевозчика (CancelModal.tsx, isCarrier=true):
     *   1 → order.cancel_reason_1
     *   2 → order.cancel_reason_2
     *   3 → свободный текст из поля `text` (хранится как есть)
     */
    private function resolveCancelReason(Request $request): ?string
    {
        /** @var Carrier $carrier */
        $carrier = $this->getUser();
        $locale = $carrier->getLocale() ?? 'en';

        $radio = (string)$request->request->get('radio', '');
        $text = trim((string)$request->request->get('text', ''));

        return match ($radio) {
            '1' => $this->translator->trans('order.cancel_reason_1', domain: 'AppBundle', locale: $locale),
            '2' => $this->translator->trans('order.cancel_reason_2', domain: 'AppBundle', locale: $locale),
            '3' => $text !== '' ? mb_substr($text, 0, 255) : $this->translator->trans('order.cancel_reason_other', domain: 'AppBundle', locale: $locale),
            default => null,
        };
    }

    #[Route('/orders/{id}/update-status', name: 'public_order_update_status', methods: ['POST'])]
    public function updateOrderStatus(string $id, Request $request, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        /** @var Carrier $user */
        $user = $this->getUser();

        $order = $this->orderRepository->find($id);
        if (!$order || $order->getCarrier() !== $user) {
            return $this->redirectToRoute('carrier_public_orders');
        }

        $token = new CsrfToken('update_order_status', (string)$request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        // Carrier: pickup → in transit → delivered. APPROVED is set in Sonata admin only.
        $allowedTransitions = [
            Order::STATUS['AWAITING_PICKUP'] => ['PICKUP_DONE' => Order::STATUS['PICKUP_DONE']],
            Order::STATUS['PICKUP_DONE'] => ['IN_TRANSIT' => Order::STATUS['IN_TRANSIT']],
            Order::STATUS['IN_TRANSIT'] => ['DELIVERED' => Order::STATUS['DELIVERED']],
        ];

        $action = (string)$request->request->get('action');
        $currentStatus = $order->getStatus();

        if (!isset($allowedTransitions[$currentStatus][$action])) {
            return $this->redirectToRoute('carrier_public_order', ['id' => $id]);
        }

        $newStatus = $allowedTransitions[$currentStatus][$action];
        $order->setStatus($newStatus);
        $this->em->flush();

        return $this->redirectToRoute('carrier_public_order', ['id' => $id]);
    }

    #[Route('/profile', name: 'public_profile')]
    public function profile(): Response
    {
        /** @var Carrier $user */
        $user = $this->getUser();

        return $this->render('public/carrier/pages/profile.html.twig', [
            'title' => $this->translator->trans('show.label_profile', domain: 'AppBundle', locale: $user->getLocale()),
            'user' => [
                ...$this->buildCarrierContext($user),
                'company_registration_number' => $user->getRegistrationNumber(),
                'company_address' => $user->getAddress(),
                'email' => $user->getEmail(),
                'phone' => $user->getPhone(),
            ],
            'orders' => $this->buildOrderStats($user),
        ]);
    }

    #[Route('/orders', name: 'public_orders', methods: ['GET'])]
    public function orders(): Response
    {
        /** @var Carrier $user */
        $user = $this->getUser();

        $listOfOrders = [];
        foreach ($this->orderRepository->findRecentByCarrier($user) as $order) {
            $history = $this->resolvePickupHistory($order);

            $listOfOrders[] = [
                'id' => $order->getId()?->toRfc4122(),
                'reference' => $order->getReference(),
                'status' => $order->getStatus(),
                'status_text' => $this->translator->trans('order.status_' . $order->getStatus(), domain: 'AppBundle', locale: $user->getLocale()),
                'price' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getBrutto(), $order->getCurrency()),
                'address' => [
                    'from' => $order->getPickupAddress(),
                    'to' => $order->getDropoutAddress(),
                ],
                'attachments' => $this->buildAttachmentList($order),
                'cargo' => $this->buildCargoList($order, $user->getLocale()),
                'stackable' => $order->isStackable(),
                'manipulator_needed' => $order->isManipulatorNeeded(),
                'comment' => $order->getNotes(),
                'pickup_date' => false !== $history ? $history->getCreatedAt()->format('d.m.Y') : null,
                'carrier' => $order->getCarrier()?->getLegalName(),
            ];
        }

        return $this->render('public/carrier/pages/orders.html.twig', [
            'title' => $this->translator->trans('show.label_orders', domain: 'AppBundle', locale: $user->getLocale()),
            'orders' => $listOfOrders,
            'user' => $this->buildCarrierContext($user),
        ]);
    }

    #[Route('/orders/{id}', name: 'public_order', methods: ['GET'])]
    public function order(string $id): Response
    {
        /** @var Carrier $user */
        $user = $this->getUser();

        $order = $this->orderRepository->find($id);
        if (!$order || $order->getCarrier() !== $user) {
            return $this->redirectToRoute('carrier_public_orders');
        }

        $history = $this->resolvePickupHistory($order);
        $paidHistory = $this->resolvePaidHistory($order);
        $deliveredHistory = $this->resolveDeliveredHistory($order);

        $item = [
            'id' => $order->getId()?->toRfc4122(),
            'reference' => $order->getReference(),
            'status' => $order->getStatus(),
            'status_text' => $this->translator->trans('order.status_' . $order->getStatus(), domain: 'AppBundle', locale: $user->getLocale()),
            'price' => $this->moneyExtension->currencyConvert($this->resolveBaseFreight($order->getLatestOffer()), $order->getCurrency()),
            'vat' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getVat(), $order->getCurrency()),
            'brutto' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getBrutto(), $order->getCurrency()),
            'fee' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getFee(), $order->getCurrency()),
            'subtotal' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getNetto(), $order->getCurrency()),
            ...$this->buildCarrierFreightPriceBreakdown($order),
            'address' => [
                'from' => $order->getPickupAddress(),
                'to' => $order->getDropoutAddress(),
            ],
            'attachments' => $this->buildAttachmentList($order),
            'cargo' => $this->buildCargoList($order, $user->getLocale()),
            'stackable' => $order->isStackable(),
            'manipulator_needed' => $order->isManipulatorNeeded(),
            'comment' => $order->getNotes(),
            'pickup_date' => false !== $history ? $history->getCreatedAt()->format('d.m.Y') : null,
            'paid_date' => false !== $paidHistory ? $paidHistory->getCreatedAt()->format(\DateTimeInterface::ATOM) : null,
            'delivered_date' => false !== $deliveredHistory ? $deliveredHistory->getCreatedAt()->format(\DateTimeInterface::ATOM) : null,
            'carrier' => $order->getCarrier()?->getLegalName(),
            'pickup_latitude' => $order->getPickupLatitude(),
            'pickup_longitude' => $order->getPickupLongitude(),
            'dropout_latitude' => $order->getDropoutLatitude(),
            'dropout_longitude' => $order->getDropoutLongitude(),
            'pickup_time_from' => $order->getPickupTimeFrom()?->format('H:i'),
            'pickup_time_to' => $order->getPickupTimeTo()?->format('H:i'),
            'delivery_time_from' => $order->getDeliveryTimeFrom()?->format('H:i'),
            'delivery_time_to' => $order->getDeliveryTimeTo()?->format('H:i'),
            'pickup_request_date' => $order->getPickupDate()?->format('d.m.Y'),
            'delivery_date' => $order->getDeliveryDate()?->format('d.m.Y'),
        ];

        return $this->render('public/carrier/pages/order.html.twig', [
            'title' => $this->translator->trans('show.label_order', domain: 'AppBundle', locale: $user->getLocale()),
            'order' => $item,
            'user' => $this->buildCarrierContext($user),
        ]);
    }

    private function buildAttachmentList(Order $order): array
    {
        return $order->getAttachments()->map(
            fn(OrderAttachment $attachment): array => [
                'filename' => $attachment->getOriginalName(),
                'path' => $this->generateUrl('file_download', ['salt' => $attachment->getSalt()]),
            ]
        )->toArray();
    }

    /**
     * Сериализует коллекцию грузов заказа в массив скалярных данных для JSON-шаблона.
     *
     * @return list<array{type: int, type_text: string, dimensions: ?string, weight: ?int, quantity: ?int, name: ?string}>
     */
    private function buildCargoList(Order $order, string $locale): array
    {
        return $order->getCargo()->map(
            fn(Cargo $cargo): array => [
                'type' => $cargo->getType(),
                'type_text' => $this->translator->trans('order.type_' . $cargo->getType(), domain: 'AppBundle', locale: $locale),
                'dimensions' => $cargo->getDimensionsCm(),
                'weight' => $cargo->getWeightKg(),
                'quantity' => $cargo->getQuantity(),
                'name' => $cargo->getName(),
            ]
        )->toArray();
    }

    /**
     * Возвращает общий контекст перевозчика для шаблонов.
     *
     * @return array{first_name: ?string, last_name: string, company_name: ?string}
     */
    private function buildCarrierContext(Carrier $user): array
    {
        return [
            'first_name' => $user->getFirstName(),
            'last_name' => substr($user->getLastName() ?? '', 0, 1) . '.',
            'company_name' => $user->getLegalName(),
        ];
    }

    /**
     * Считает заказы перевозчика по ключевым статусам и вычисляет статистику профиля.
     *
     * delivery_percent — доля DELIVERED/APPROVED среди завершённых (DELIVERED + APPROVED + CANCELLED), макс. 100.
     * approval_percent — доля ACCEPTED среди всех ответов на назначения (ACCEPTED + REJECTED), макс. 100.
     *
     * @return array{total: int, cancelled: int, delivered: int, in_progress: int, delivery_percent: int, approval_percent: int}
     */
    private function buildOrderStats(Carrier $carrier): array
    {
        $stats = [
            'total' => $carrier->getOrders()->count(),
            'cancelled' => 0,
            'delivered' => 0,
            'in_progress' => 0,
        ];

        foreach ($carrier->getOrders() as $order) {
            match ($order->getStatus()) {
                Order::STATUS['CANCELLED'] => $stats['cancelled']++,
                Order::STATUS['DELIVERED'], Order::STATUS['APPROVED'] => $stats['delivered']++,
                default => $stats['in_progress']++,
            };
        }

        $completedTotal = $stats['delivered'] + $stats['cancelled'];
        $stats['delivery_percent'] = $completedTotal > 0
            ? (int)round(min($stats['delivered'] / $completedTotal * 100, 100))
            : 0;

        $assignmentCounts = $this->orderAssignmentRepository->countAcceptedAndRejectedByCarrier($carrier);
        $assignmentTotal = $assignmentCounts['accepted'] + $assignmentCounts['rejected'];
        $stats['approval_percent'] = $assignmentTotal > 0
            ? (int)round(min($assignmentCounts['accepted'] / $assignmentTotal * 100, 100))
            : 0;

        return $stats;
    }

    /**
     * Ищет в истории заказа запись о факте забора груза (PICKUP_DONE).
     */
    private function resolvePickupHistory(Order $order): OrderHistory|false
    {
        return $order->getHistories()
            ->filter(fn(OrderHistory $history) => $history->getStatus() === Order::STATUS['PICKUP_DONE'])
            ->first();
    }

    /**
     * Ищет в истории заказа запись о факте оплаты заказа (PAID).
     */
    private function resolvePaidHistory(Order $order): OrderHistory|false
    {
        return $order->getHistories()
            ->filter(fn(OrderHistory $history) => $history->getStatus() === Order::STATUS['PAID'])
            ->first();
    }

    /**
     * Ищет в истории заказа запись о факте доставки (DELIVERED).
     */
    private function resolveDeliveredHistory(Order $order): OrderHistory|false
    {
        return $order->getHistories()
            ->filter(fn(OrderHistory $history) => $history->getStatus() === Order::STATUS['DELIVERED'])
            ->first();
    }

    /**
     * Поля для блока цены у перевозчика: PVN только от base fee; total = base + этот PVN.
     * Ставка «21%» пока совпадает с {@see SenderOrderPayableTotalCentsCalculator}; позже — из профиля перевозчика.
     *
     * @return array{
     *     carrier_freight_vat_rate_display: string,
     *     carrier_freight_vat: ?string,
     *     carrier_freight_total: ?string,
     * }
     */
    private function buildCarrierFreightPriceBreakdown(Order $order): array
    {
        $currency = $order->getCurrency() ?? 'EUR';
        $parts = $this->senderOrderPayableTotalCentsCalculator->computeCarrierFreightOnlyVatAndGrossCents(
            $order->getLatestOffer()
        );

        return [
            'carrier_freight_vat_rate_display' => '21%',
            'carrier_freight_vat' => $parts !== null
                ? $this->moneyExtension->currencyConvert($parts['vat_cents'], $currency)
                : null,
            'carrier_freight_total' => $parts !== null
                ? $this->moneyExtension->currencyConvert($parts['gross_cents'], $currency)
                : null,
        ];
    }

    /**
     * Возвращает базовый фрахт (без комиссии платформы и без НДС).
     *
     * base_freight = netto - fee
     * Это позволяет корректно отобразить комиссию как % именно от базовой стоимости.
     */
    private function resolveBaseFreight(?OrderOffer $offer): ?int
    {
        if (!$offer) {
            return null;
        }

        $netto = $offer->getNetto();
        $fee = $offer->getFee();

        if ($netto === null) {
            return null;
        }

        return $fee !== null ? $netto - $fee : $netto;
    }
}

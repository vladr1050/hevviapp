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

use App\Entity\Carrier;
use App\Entity\Order;
use App\Entity\OrderHistory;
use App\Entity\OrderOffer;
use App\Repository\OrderRepository;
use App\Twig\Extension\Filter\MoneyExtension;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/carrier', name: 'carrier_')]
#[IsGranted('ROLE_CARRIER')]
class CarrierController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository        $orderRepository,
        private readonly MoneyExtension         $moneyExtension,
        private readonly TranslatorInterface    $translator,
        private readonly EntityManagerInterface $em,
    )
    {
    }
    #[Route('/requests', name: 'public_requests')]
    public function requests(): Response
    {
        /** @var Carrier $user */
        $user = $this->getUser();

        $listOfOrders = [];
        foreach ($this->orderRepository->findRecentByCarrier($user) as $order) {
            $history = $this->resolvePickupHistory($order);
            $cargo = $order->getCargo()->first();

            $listOfOrders[] = [
                'id' => $order->getId()?->toRfc4122(),
                'status' => $order->getStatus(),
                'status_text' => $this->translator->trans('order.status_' . $order->getStatus(), domain: 'AppBundle', locale: $user->getLocale()),
                'price' => $this->moneyExtension->currencyConvert($this->resolveBaseFreight($order->getLatestOffer()), $order->getCurrency()),
                'vat' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getVat(), $order->getCurrency()),
                'brutto' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getBrutto(), $order->getCurrency()),
                'fee' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getFee(), $order->getCurrency()),
                'address' => [
                    'from' => $order->getPickupAddress(),
                    'to' => $order->getDropoutAddress(),
                ],
                'name' => $cargo?->getName(),
                'item' => $cargo?->getQuantity(),
                'type' => $this->translator->trans('order.type_' . $cargo?->getType(), domain: 'AppBundle', locale: $user->getLocale()),
                'cargoDimensions' => $cargo?->getDimensionsCm(),
                'cargoWeight' => $cargo?->getWeightKg(),
                'comment' => $order->getNotes(),
                'pickup_date' => false !== $history ? $history->getCreatedAt()->format('d.m.Y') : null,
                'pickup_latitude' => $order->getPickupLatitude(),
                'pickup_longitude' => $order->getPickupLongitude(),
                'dropout_latitude' => $order->getDropoutLatitude(),
                'dropout_longitude' => $order->getDropoutLongitude(),
                'stackable' => $cargo?->isStackable(),
                'manipulator_needed' => $cargo?->isManipulatorNeeded(),
                'pickup_time_from' => $order->getPickupTimeFrom()?->format('H:i'),
                'pickup_time_to' => $order->getPickupTimeTo()?->format('H:i'),
                'delivery_time_from' => $order->getDeliveryTimeFrom()?->format('H:i'),
                'delivery_time_to' => $order->getDeliveryTimeTo()?->format('H:i'),
                'pickup_request_date' => $order->getPickupDate()?->format('d.m.Y'),
                'delivery_date' => $order->getDeliveryDate()?->format('d.m.Y'),
                /* FIXME PAVEL добавь имя клиента
                
                    sender: {
                        first_name: string, 
                        last_name: string
                    }
                */


            ];
        }

        return $this->render('public/carrier/pages/requests.html.twig', [
            'title' => $this->translator->trans('show.label_requests', domain: 'AppBundle', locale: $user->getLocale()),
            'user' => $this->buildCarrierContext($user),
            'orders' => $listOfOrders,
        ]);
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
            $cargo = $order->getCargo()->first();

            $listOfOrders[] = [
                'id' => $order->getId()?->toRfc4122(),
                'status' => $order->getStatus(),
                'status_text' => $this->translator->trans('order.status_' . $order->getStatus(), domain: 'AppBundle', locale: $user->getLocale()),
                'price' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getBrutto(), $order->getCurrency()),
                'address' => [
                    'from' => $order->getPickupAddress(),
                    'to' => $order->getDropoutAddress(),
                ],
                'item' => $cargo?->getQuantity(),
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
        if (!$order || $order->getSender() !== $user) {
            return $this->redirectToRoute('carrier_public_orders');
        }

        $history = $this->resolvePickupHistory($order);
        $cargo = $order->getCargo()->first();

        $item = [
            'id' => $order->getId()?->toRfc4122(),
            'status' => $order->getStatus(),
            'status_text' => $this->translator->trans('order.status_' . $order->getStatus(), domain: 'AppBundle', locale: $user->getLocale()),
            'price' => $this->moneyExtension->currencyConvert($this->resolveBaseFreight($order->getLatestOffer()), $order->getCurrency()),
            'vat' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getVat(), $order->getCurrency()),
            'brutto' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getBrutto(), $order->getCurrency()),
            'fee' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getFee(), $order->getCurrency()),
            'address' => [
                'from' => $order->getPickupAddress(),
                'to' => $order->getDropoutAddress(),
            ],
            'name' => $cargo?->getName(),
            'item' => $cargo?->getQuantity(),
            'type' => $this->translator->trans('order.type_' . $cargo?->getType(), domain: 'AppBundle', locale: $user->getLocale()),
            'cargoDimensions' => $cargo?->getDimensionsCm(),
            'cargoWeight' => $cargo?->getWeightKg(),
            'comment' => $order->getNotes(),
            'pickup_date' => false !== $history ? $history->getCreatedAt()->format('d.m.Y') : null,
            'carrier' => $order->getCarrier()?->getLegalName(),
            'pickup_latitude' => $order->getPickupLatitude(),
            'pickup_longitude' => $order->getPickupLongitude(),
            'dropout_latitude' => $order->getDropoutLatitude(),
            'dropout_longitude' => $order->getDropoutLongitude(),
            'stackable' => $cargo?->isStackable(),
            'manipulator_needed' => $cargo?->isManipulatorNeeded(),
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
     * Считает заказы перевозчика по ключевым статусам.
     *
     * @return array{total: int, cancelled: int, delivered: int, in_progress: int}
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
                Order::STATUS['DELIVERED'] => $stats['delivered']++,
                default => $stats['in_progress']++,
            };
        }

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
        $fee   = $offer->getFee();

        if ($netto === null) {
            return null;
        }

        return $fee !== null ? $netto - $fee : $netto;
    }
}

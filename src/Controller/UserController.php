<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Twig\Extension\Filter\MoneyExtension;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/user', name: 'user_')]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MoneyExtension         $moneyExtension,
    )
    {
    }

    #[Route('/dashboard', name: 'public_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('public/user/pages/dashboard.html.twig', [
            'title' => 'Dashboard',
        ]);
    }

    #[Route('/profile', name: 'public_profile')]
    public function profile(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $orders = [
            'total' => $user->getOrders()->count(),
            'cancelled' => 0,
            'delivered' => 0,
            'in_progress' => 0,
        ];

        foreach ($user->getOrders() as $order) {
            switch ($order->getStatus()) {
                case Order::STATUS['CANCELLED']:
                    $orders['cancelled']++;
                    break;
                case Order::STATUS['DELIVERED']:
                    $orders['delivered']++;
                    break;
                default:
                    $orders['in_progress']++;
                    break;
            }
        }

        $profile = [
            'first_name' => $user?->getFirstName(),
            'last_name' => $user?->getLastName(),
            'company_name' => $user?->getCompanyName(),
            'company_registration_number' => $user?->getCompanyRegistrationNumber(),
            'company_address' => $user?->getCompanyAddress(),
            'email' => $user?->getEmail(),
            'phone' => $user?->getPhone(),
        ];

        return $this->render('public/user/pages/profile.html.twig', [
            'title' => 'Profile',
            'user' => $profile,
            'orders' => $orders,
        ]);
    }

    #[Route('/requests', name: 'public_requests')]
    public function requests(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render('public/user/pages/requests.html.twig', [
            'title' => 'Requests',
            'user' => [
                'first_name' => $user?->getFirstName(),
                'last_name' => $user?->getLastName(),
                'company_name' => $user?->getCompanyName(),
            ]
        ]);
    }

    #[Route('/orders', name: 'public_orders', methods: ['GET'])]
    public function orders(TranslatorInterface $translator): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $orders = $this->em->getRepository(Order::class)->findBy([
            'sender' => $user,
        ], ['createdAt' => 'DESC'], 10, 0);

        $listOfOrders = [];
        foreach ($orders as $order) {
            $history = $order->getHistories()->filter(fn($history) => $history->getStatus() === Order::STATUS['PICKUP_DONE'])->first();

            $listOfOrders[] = [
                'id' => $order->getId()?->toRfc4122(),
                'status' => $order->getStatus(),
                'status_text' => $translator->trans('order.status_' . $order->getStatus(), domain: 'AppBundle', locale: $user?->getLocale()),
                'price' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getBrutto(), $order->getCurrency()),
                'address' => [
                    'from' => $order->getPickupAddress(),
                    'to' => $order->getDropoutAddress(),
                ],
                'item' => $order->getCargo()->count(),
                'comment' => $order->getNotes(),
                'pickup_date' => $history?->getCreatedAt()->format('d.m.Y'),
                'carrier' => $order->getCarrier()?->getLegalName(),
            ];
        }

        return $this->render('public/user/pages/orders.html.twig', [
            'title' => 'Orders',
            'orders' => $listOfOrders,
            'user' => [
                'first_name' => $user?->getFirstName(),
                'last_name' => $user?->getLastName(),
                'company_name' => $user?->getCompanyName(),
            ]
        ]);
    }

    #[Route('/orders/{id}', name: 'public_order', methods: ['GET'])]
    public function order(string $id, TranslatorInterface $translator): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $order = $this->em->getRepository(Order::class)->find($id);
        if (!$order) {
            return $this->redirectToRoute('user_public_orders');
        }

        $history = $order->getHistories()->filter(fn($history) => $history->getStatus() === Order::STATUS['PICKUP_DONE'])->first();
        $cargo = $order->getCargo()->first();

        $item = [
            'id' => $order->getId()?->toRfc4122(),
            'status' => $order->getStatus(),
            'status_text' => $translator->trans('order.status_' . $order->getStatus(), domain: 'AppBundle', locale: $user?->getLocale()),
            'price' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getBrutto(), $order->getCurrency()),
            'address' => [
                'from' => $order->getPickupAddress(),
                'to' => $order->getDropoutAddress(),
            ],
            'name' => $cargo?->getName(),
            'item' => $cargo?->getQuantity(),
            'cargoDimensions' => $cargo?->getDimensionsCm(),
            'cargoWeight' => $cargo?->getWeightKg(),
            'comment' => $order->getNotes(),
            'pickup_date' => $history?->getCreatedAt()->format('d.m.Y'),
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

        return $this->render('public/user/pages/order.html.twig', [
            'title' => 'Order',
            'order' => $item,
            'user' => [
                'first_name' => $user?->getFirstName(),
                'last_name' => $user?->getLastName(),
                'company_name' => $user?->getCompanyName(),
            ]
        ]);
    }
}

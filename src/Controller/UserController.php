<?php

namespace App\Controller;

use App\Entity\Cargo;
use App\Entity\Order;
use App\Entity\OrderAttachment;
use App\Entity\OrderHistory;
use App\Entity\OrderOffer;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Service\Billing\IssuingCompanyResolver;
use App\Service\Invoice\InvoiceIssuingService;
use App\Service\OrderAttachmentUploader;
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

#[Route('/user', name: 'user_')]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    /** @var float VAT on freight for sender UI; replace with carrier-specific rate when assigned. */
    private const SENDER_FREIGHT_VAT_PERCENT = 21.0;

    public function __construct(
        private readonly OrderRepository           $orderRepository,
        private readonly MoneyExtension            $moneyExtension,
        private readonly TranslatorInterface       $translator,
        private readonly EntityManagerInterface    $em,
        private readonly OrderAttachmentUploader   $attachmentUploader,
        private readonly InvoiceIssuingService     $invoiceIssuingService,
        private readonly IssuingCompanyResolver    $issuingCompanyResolver,
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

    #[Route('/file', name: 'public_file')]
    public function filePage(): Response
    {
        return $this->render('public/user/pages/file.html.twig', [
            'invoice' => [
                'number' => 'LV 0000000',
                'date' => '21.03.2026',
                'pickup_address' => 'Mūkusalas 3, Rīga',
                'delivery_address' => 'IKEA bāze, Rīga',
                'seller_name' => 'Vlad SIA',
                'seller_reg' => 'XXXXXXXXX',
                'seller_vat' => 'LVXXXXXXXX',
                'seller_address' => ['Mūkusalas 3, Rīga', 'IKEA bāze, Rīga', 'IKEA bāze, Rīga'],
                'seller_email' => 'billing@hevvi.app',
                'seller_phone' => '+371 XXXXXXXX',
                'buyer_first_name' => 'Vārds',
                'buyer_last_name' => 'Uzvārds',
                'buyer_company_name' => 'Uzņēmuma nosaukums',
                'buyer_reg' => 'XXXXXXXXX',
                'buyer_vat' => 'LVXXXXXXXX',
                'buyer_address' => ['Mūkusalas 3, Rīga', 'IKEA bāze, Rīga', 'IKEA bāze, Rīga'],
                'service_date' => '21.03.2026',
                'amount_freight' => '100.00',
                'amount_net' => '100.00',
                'amount_commission' => '10.00',
                'amount_subtotal' => '100.00',
                'amount_vat' => '21.00',
                'amount_total' => '121.00',
                'bank_card' => 'Bankas karte',
                'bank_transfer' => 'Bankas pārskaitījums',
                'payment_due_date' => '04.04.2026',
            ],
        ]);
    }

    #[Route('/profile', name: 'public_profile')]
    public function profile(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('public/user/pages/profile.html.twig', [
            'title' => $this->translator->trans('show.label_profile', domain: 'AppBundle', locale: $user->getLocale()),
            'user' => [
                ...$this->buildUserContext($user),
                'company_registration_number' => $user->getCompanyRegistrationNumber(),
                'company_address' => $user->getCompanyAddress(),
                'email' => $user->getEmail(),
                'phone' => $user->getPhone(),
            ],
            'orders' => $this->buildOrderStats($user),
        ]);
    }

    #[Route('/requests', name: 'public_requests')]
    public function requests(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $listOfOrders = [];
        foreach ($this->orderRepository->findRecentBySender($user, 5) as $order) {
            $listOfOrders[] = [
                'id' => $order->getId()?->toRfc4122(),
                'reference' => $order->getReference(),
                'address' => [
                    'from' => $order->getPickupAddress(),
                    'to' => $order->getDropoutAddress(),
                ],
                'attachments' => $this->buildAttachmentList($order),
                'cargo' => $this->buildCargoList($order, $user->getLocale()),
                'stackable' => $order->isStackable(),
                'manipulator_needed' => $order->isManipulatorNeeded(),
                'comment' => $order->getNotes(),
            ];
        }

        return $this->render('public/user/pages/requests.html.twig', [
            'title' => $this->translator->trans('show.label_requests', domain: 'AppBundle', locale: $user->getLocale()),
            'user' => $this->buildUserContext($user),
            'orders' => $listOfOrders,
        ]);
    }

    #[Route('/orders', name: 'public_orders', methods: ['GET'])]
    public function orders(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $listOfOrders = [];
        foreach ($this->orderRepository->findRecentBySender($user) as $order) {
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

        return $this->render('public/user/pages/orders.html.twig', [
            'title' => $this->translator->trans('show.label_orders', domain: 'AppBundle', locale: $user->getLocale()),
            'orders' => $listOfOrders,
            'user' => $this->buildUserContext($user),
        ]);
    }

    #[Route('/orders/{id}', name: 'public_order', methods: ['GET'])]
    public function order(string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $order = $this->orderRepository->find($id);
        if (!$order || $order->getSender() !== $user) {
            return $this->redirectToRoute('user_public_orders');
        }

        $history = $this->resolvePickupHistory($order);

        $item = [
            'id' => $order->getId()?->toRfc4122(),
            'reference' => $order->getReference(),
            'status' => $order->getStatus(),
            'status_text' => $this->translator->trans('order.status_' . $order->getStatus(), domain: 'AppBundle', locale: $user->getLocale()),
            'price' => $this->moneyExtension->currencyConvert($this->resolveBaseFreight($order->getLatestOffer()), $order->getCurrency()),
            'vat' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getVat(), $order->getCurrency()),
            'brutto' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getBrutto(), $order->getCurrency()),
            'fee' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getFee(), $order->getCurrency()),
            'sender_total' => $this->computeSenderOrderTotalDisplay($order),
            // netto в OrderOffer = base + platform fee (до НДС), см. OrderOfferCalculatorService; не суммировать с fee повторно
            'subtotal' => $this->moneyExtension->currencyConvert($order->getLatestOffer()?->getNetto(), $order->getCurrency()),
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

        return $this->render('public/user/pages/order.html.twig', [
            'title' => $this->translator->trans('show.label_order', domain: 'AppBundle', locale: $user->getLocale()),
            'order' => $item,
            'user' => $this->buildUserContext($user),
        ]);
    }

    #[Route('/orders/{id}/confirm', name: 'confirm_order', methods: ['POST'])]
    public function confirmOrder(string $id, Request $request, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        $token = new CsrfToken('confirm_order', (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $user */
        $user = $this->getUser();

        $order = $this->orderRepository->find($id);
        if (!$order || $order->getSender() !== $user) {
            return $this->redirectToRoute('user_public_orders');
        }

        if ($order->getStatus() !== Order::STATUS['OFFERED']) {
            return $this->redirectToRoute('user_public_order', ['id' => $id]);
        }

        $offerResult = $this->applyOfferStatus($order, OrderOffer::STATUS['ACCEPTED']);
        if (null !== $offerResult) {
            return $offerResult;
        }

        $orderResult = $this->applyOrderStatus($order, Order::STATUS['ACCEPTED']);
        if (null !== $orderResult) {
            return $orderResult;
        }

        $this->em->flush();

        $this->invoiceIssuingService->issueForAcceptedOrder($order);

        return $this->redirectToRoute('user_public_order', ['id' => $id]);
    }

    #[Route('/orders/{id}/cancel', name: 'cancel_order', methods: ['POST'])]
    public function cancelOrder(string $id, Request $request, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        $token = new CsrfToken('cancel_order', (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $user */
        $user = $this->getUser();

        $order = $this->orderRepository->find($id);
        if (!$order || $order->getSender() !== $user) {
            return $this->redirectToRoute('user_public_orders');
        }

        $cancellableStatuses = [
            Order::STATUS['DRAFT'],
            Order::STATUS['OFFERED'],
            Order::STATUS['ACCEPTED'],
        ];
        if (!in_array($order->getStatus(), $cancellableStatuses, true)) {
            return $this->redirectToRoute('user_public_order', ['id' => $id]);
        }

        $order->setStatus(Order::STATUS['CANCELLED']);
        $order->setCancelReason($this->resolveCancelReason($request, $user->getLocale()));

        $this->em->flush();

        return $this->redirectToRoute('user_public_orders');
    }

    /**
     * Отмена черновика / котировки до оплаты: полное удаление заказа и редирект на Requests.
     */
    #[Route('/orders/{id}/abandon', name: 'abandon_order', methods: ['POST'])]
    public function abandonOrder(string $id, Request $request, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        $token = new CsrfToken('abandon_order', (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $user */
        $user = $this->getUser();

        $order = $this->orderRepository->find($id);
        if (!$order || $order->getSender() !== $user) {
            return $this->redirectToRoute('user_public_requests');
        }

        if (!in_array($order->getStatus(), [Order::STATUS['DRAFT'], Order::STATUS['OFFERED']], true)) {
            return $this->redirectToRoute('user_public_order', ['id' => $id]);
        }

        foreach ($order->getAttachments()->toArray() as $attachment) {
            $this->attachmentUploader->delete($attachment);
        }

        $this->em->remove($order);
        $this->em->flush();

        return $this->redirectToRoute('user_public_requests');
    }

    /**
     * Собирает переведённую причину отмены из полей формы CancelModal.
     *
     * Radio-варианты для отправителя (CancelModal.tsx, isCarrier=false):
     *   1 → order.cancel_reason_1
     *   2 → order.cancel_reason_2
     *   3 → свободный текст из поля `text` (хранится как есть)
     */
    private function resolveCancelReason(Request $request, ?string $locale): ?string
    {
        $locale ??= 'en';
        $radio = (string) $request->request->get('radio', '');
        $text = trim((string) $request->request->get('text', ''));

        return match ($radio) {
            '1' => $this->translator->trans('order.cancel_reason_1', domain: 'AppBundle', locale: $locale),
            '2' => $this->translator->trans('order.cancel_reason_2', domain: 'AppBundle', locale: $locale),
            '3' => $text !== '' ? mb_substr($text, 0, 255) : $this->translator->trans('order.cancel_reason_other', domain: 'AppBundle', locale: $locale),
            default => null,
        };
    }

    private function applyOrderStatus(Order $order, int $status): ?Response
    {
        if ($order->getStatus() !== Order::STATUS['OFFERED']) {
            return $this->redirectToRoute('user_public_order', ['id' => $order->getId()]);
        }

        $order->setStatus($status);
        return null;
    }

    private function applyOfferStatus(Order $order, int $status): ?Response
    {
        $latestOffer = $order->getLatestOffer();
        if (!$latestOffer || $latestOffer->getStatus() !== OrderOffer::STATUS['DRAFT']) {
            return $this->redirectToRoute('user_public_order', ['id' => $order->getId()]);
        }

        $latestOffer->setStatus($status);
        return null;
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
     * Возвращает общий контекст пользователя для шаблонов.
     *
     * @return array{first_name: ?string, last_name: string, company_name: ?string}
     */
    private function buildUserContext(User $user): array
    {
        return [
            'first_name' => $user->getFirstName(),
            'last_name' => substr($user->getLastName() ?? '', 0, 1) . '.',
            'company_name' => $user->getCompanyName(),
        ];
    }

    /**
     * Считает заказы пользователя по ключевым статусам.
     *
     * @return array{total: int, cancelled: int, delivered: int, in_progress: int}
     */
    private function buildOrderStats(User $user): array
    {
        $stats = [
            'total' => $user->getOrders()->count(),
            'cancelled' => 0,
            'delivered' => 0,
            'in_progress' => 0,
        ];

        foreach ($user->getOrders() as $order) {
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
        $fee = $offer->getFee();

        if ($netto === null) {
            return null;
        }

        return $fee !== null ? $netto - $fee : $netto;
    }

    /**
     * Total for sender confirmation UI: (base + VAT on base) + (platform fee + VAT on fee).
     * VAT on freight is fixed for now; VAT on fee follows issuing BillingCompany (operator).
     */
    private function computeSenderOrderTotalDisplay(Order $order): ?string
    {
        $offer = $order->getLatestOffer();
        $baseCents = $this->resolveBaseFreight($offer);
        if ($baseCents === null) {
            return null;
        }

        $feeCents = (int) ($offer?->getFee() ?? 0);
        $issuer = $this->issuingCompanyResolver->getIssuingCompany();
        $issuerVatPercent = 0.0;
        if ($issuer !== null) {
            $rate = $issuer->getVatRate();
            if ($rate !== null && trim((string) $rate) !== '') {
                $issuerVatPercent = (float) $rate;
            }
        }

        $freightVatCents = (int) round($baseCents * self::SENDER_FREIGHT_VAT_PERCENT / 100.0);
        $platformVatCents = (int) round($feeCents * $issuerVatPercent / 100.0);
        $totalCents = $baseCents + $freightVatCents + $feeCents + $platformVatCents;

        return $this->moneyExtension->currencyConvert($totalCents, $order->getCurrency());
    }
}

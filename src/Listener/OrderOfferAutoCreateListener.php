<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Listener;

use App\Entity\Order;
use App\Entity\OrderOffer;
use App\Service\OrderOffer\Contract\OrderOfferCalculatorInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * OrderOffer Auto Create Listener
 *
 * Автоматически создает OrderOffer для Order после сохранения.
 * Простой подход через EntityListener.
 */
#[AsEntityListener(event: Events::postPersist, entity: Order::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Order::class)]
class OrderOfferAutoCreateListener
{
    public function __construct(
        private readonly OrderOfferCalculatorInterface $calculator,
        private readonly LoggerInterface               $logger,
        private readonly TranslatorInterface           $translator,
        private readonly RequestStack                  $requestStack,
    )
    {
    }

    /**
     * После создания Order
     */
    public function postPersist(Order $order, PostPersistEventArgs $args): void
    {
        $this->logger->info('🆕 Order postPersist event triggered', [
            'order_id' => $order->getId()?->toRfc4122(),
        ]);

        $this->createOrderOfferIfNeeded($order, $args);
    }

    /**
     * После обновления Order
     */
    public function postUpdate(Order $order, PostUpdateEventArgs $args): void
    {
        $this->logger->info('🔄 Order postUpdate event triggered', [
            'order_id' => $order->getId()?->toRfc4122(),
        ]);

        $this->createOrderOfferIfNeeded($order, $args);
    }

    /**
     * Создать OrderOffer если нужно
     */
    private function createOrderOfferIfNeeded(Order $order, PostPersistEventArgs|PostUpdateEventArgs $args): void
    {
        $this->logger->info('🔍 Checking OrderOffer creation', [
            'order_id' => $order->getId()?->toRfc4122(),
            'status' => $order->getStatus(),
            'offers_count' => $order->getOffers()->count(),
            'latitude' => $order->getDropoutLatitude(),
            'longitude' => $order->getDropoutLongitude(),
        ]);

        // Если статус уже OFFERED или выше - значит уже обработали
        if ($order->getStatus() >= Order::STATUS['OFFERED']) {
            $this->logger->info('⏭️ Order status is OFFERED or higher, skipping');
            return;
        }

        // Проверяем, есть ли уже OrderOffer
        if ($order->getOffers()->count() > 0) {
            $this->logger->info('⏭️ Order already has offers, skipping');
            return;
        }

        // Проверяем наличие координат
        if (!$order->getDropoutLatitude() || !$order->getDropoutLongitude()) {
            $this->logger->warning('⚠️ Missing coordinates, skipping');

            $this->addFlashMessage(
                'info',
                $this->translator->trans(
                    'order_offer.warning.missing_coordinates_for_calculation',
                    [],
                    'AppBundle'
                )
            );

            return;
        }

        $this->logger->info('🚀 Starting calculation');

        // Рассчитываем
        $result = $this->calculator->calculate($order);

        if (!$result->success) {
            $this->logger->warning('⚠️ Calculation failed', [
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
            ]);

            $this->addFlashMessage(
                'warning',
                $this->translator->trans(
                    'order_offer.warning.auto_create_failed',
                    [
                        '%reason%' => $this->translator->trans(
                            $result->errorMessage ?? 'order_offer.error.unknown',
                            [],
                            'AppBundle'
                        ),
                    ],
                    'AppBundle'
                )
            );

            return;
        }

        // Создаем OrderOffer
        $orderOffer = new OrderOffer();
        $orderOffer->setRelatedOrder($order);
        $orderOffer->setBrutto($result->bruttoPrice);
        $orderOffer->setNetto($result->nettoPrice);
        $orderOffer->setVat($result->vatPercent);
        $orderOffer->setStatus(OrderOffer::STATUS['DRAFT']);

        $order
            ->setCurrency($result->currency)
            ->setStatus(Order::STATUS['OFFERED']);

        $em = $args->getObjectManager();
        $em->persist($orderOffer);
        $em->flush();

        $this->addFlashMessage(
            'success',
            $this->translator->trans(
                'order_offer.success.auto_created',
                [],
                'AppBundle'
            )
        );

        $this->logger->info('✅ OrderOffer created successfully', [
            'order_id' => $order->getId()?->toRfc4122(),
            'offer_id' => $orderOffer->getId()?->toRfc4122(),
            'brutto' => $result->bruttoPrice,
            'netto' => $result->nettoPrice,
        ]);
    }

    /**
     * Добавить flash сообщение
     */
    private function addFlashMessage(string $type, string $message): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $flashBag = $session->getFlashBag();

        if ($flashBag instanceof FlashBagInterface) {
            $flashBag->add($type, $message);
        }
    }
}

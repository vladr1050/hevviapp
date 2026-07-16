<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Service\OrderOffer;

use App\Entity\Order;
use App\Entity\OrderOffer;
use App\Service\OrderOffer\Contract\OrderOfferCalculatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Creates OrderOffer for draft orders when coordinates are present.
 */
class OrderOfferAutoCreateService
{
    public function __construct(
        private readonly OrderOfferCalculatorInterface $calculator,
        private readonly LoggerInterface               $logger,
        private readonly TranslatorInterface           $translator,
        private readonly RequestStack                  $requestStack,
    ) {
    }

    public function createIfNeeded(Order $order, EntityManagerInterface $em): void
    {
        $this->logger->info('🔍 Checking OrderOffer creation', [
            'order_id' => $order->getId()?->toRfc4122(),
            'status' => $order->getStatus(),
            'offers_count' => $order->getOffers()->count(),
            'latitude' => $order->getDropoutLatitude(),
            'longitude' => $order->getDropoutLongitude(),
        ]);

        if ($order->consumeSkipNextOfferAutoCreate()) {
            $this->logger->info('⏭️ Skip offer auto-create flag (quote void / edit flow)');

            return;
        }

        if ($order->getStatus() >= Order::STATUS['OFFERED']) {
            $this->logger->info('⏭️ Order status is OFFERED or higher, skipping');

            return;
        }

        if ($order->getOffers()->count() > 0) {
            $this->logger->info('⏭️ Order already has offers, skipping');

            return;
        }

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

        $orderOffer = new OrderOffer();
        $orderOffer->setRelatedOrder($order);
        $orderOffer->setBrutto($result->bruttoPrice);
        $orderOffer->setNetto($result->nettoPrice);
        $orderOffer->setVat($result->vatAmount);
        $orderOffer->setFee($result->feeAmount);
        $orderOffer->setStatus(OrderOffer::STATUS['DRAFT']);

        $order
            ->setCurrency($result->currency)
            ->setStatus(Order::STATUS['OFFERED']);

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
            'fee' => $result->feeAmount,
            'fee_percent' => $result->feePercent,
            'vat_percent' => $result->vatPercent,
        ]);
    }

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

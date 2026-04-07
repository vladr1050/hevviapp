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

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\OrderOffer;
use App\Service\OrderOffer\Contract\OrderOfferCalculatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class OrderOfferController
 * 
 * Контроллер для управления OrderOffer в административной панели.
 * Следует принципам SOLID:
 * - Single Responsibility: отвечает только за HTTP обработку запросов OrderOffer
 * - Dependency Inversion: зависит от интерфейсов, а не от реализаций
 */
#[Route('/admin/order-offer', name: 'admin_order_offer_')]
class OrderOfferController extends AbstractController
{
    public function __construct(
        private readonly OrderOfferCalculatorInterface $calculator,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Создать OrderOffer для заказа.
     * 
     * Endpoint для AJAX запроса из админки при нажатии кнопки "Добавить" в коллекции OrderOffer.
     * 
     * @param Order $order Заказ для которого создается предложение
     * 
     * @return JsonResponse JSON ответ с результатом
     */
    #[Route('/calculate/{id}', name: 'calculate', methods: ['POST'])]
    public function calculate(Order $order): JsonResponse
    {
        // Расчет стоимости
        $result = $this->calculator->calculate($order);

        if (!$result->success) {
            return $this->json([
                'success' => false,
                'message' => $this->translator->trans(
                    $result->errorMessage ?? 'order_offer.error.unknown',
                    [],
                    'AppBundle'
                ),
                'errorCode' => $result->errorCode,
            ], Response::HTTP_BAD_REQUEST);
        }

        // Создание OrderOffer
        $orderOffer = new OrderOffer();
        $orderOffer->setRelatedOrder($order);
        $orderOffer->setBrutto($result->bruttoPrice);
        $orderOffer->setNetto($result->nettoPrice);
        $orderOffer->setVat($result->vatAmount);
        $orderOffer->setFee($result->feeAmount);
        $orderOffer->setStatus(OrderOffer::STATUS['ACCEPTED']); // По умолчанию accepted

        $this->entityManager->persist($orderOffer);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans(
                'order_offer.success.calculated',
                [],
                'AppBundle'
            ),
            'data' => [
                'id' => $orderOffer->getId()?->toRfc4122(),
                'brutto' => $orderOffer->getBrutto(),
                'netto' => $orderOffer->getNetto(),
                'fee' => $orderOffer->getFee(),
                'vat' => $orderOffer->getVat(),
                'vatPercent' => $result->vatPercent,
                'status' => $orderOffer->getStatus(),
            ],
        ]);
    }

    /**
     * Проверить возможность расчета OrderOffer для заказа.
     * 
     * Endpoint для проверки перед добавлением OrderOffer.
     * Возвращает информацию о том, можно ли рассчитать цену.
     * 
     * @param Order $order Заказ для проверки
     * 
     * @return JsonResponse JSON ответ с результатом проверки
     */
    #[Route('/check/{id}', name: 'check', methods: ['GET'])]
    public function check(Order $order): JsonResponse
    {
        $result = $this->calculator->calculate($order);

        return $this->json([
            'success' => $result->success,
            'canCalculate' => $result->success,
            'message' => $result->success
                ? $this->translator->trans('order_offer.success.can_calculate', [], 'AppBundle')
                : $this->translator->trans($result->errorMessage ?? 'order_offer.error.unknown', [], 'AppBundle'),
            'errorCode' => $result->errorCode,
        ]);
    }
}

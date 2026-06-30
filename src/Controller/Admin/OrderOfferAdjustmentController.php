<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Manager;
use App\Entity\Order;
use App\Entity\OrderOffer;
use App\Enum\OfferPriceAdjustmentMode;
use App\Form\Type\OfferPriceAdjustmentType;
use App\Service\Order\SenderOrderPayableTotalCentsCalculator;
use App\Repository\OrderRepository;
use App\Service\OrderOffer\DTO\OfferPriceAdjustmentInput;
use App\Service\OrderOffer\OfferPriceAdjustmentService;
use Sonata\AdminBundle\Admin\Pool;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/order', name: 'admin_order_')]
#[IsGranted('ROLE_ADMIN')]
final class OrderOfferAdjustmentController extends AbstractController
{
    public function __construct(
        private readonly OfferPriceAdjustmentService $adjustmentService,
        private readonly SenderOrderPayableTotalCentsCalculator $senderOrderPayableTotalCentsCalculator,
        private readonly OrderRepository $orderRepository,
        private readonly Pool $adminPool,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/{id}/adjust-offer', name: 'adjust_offer', methods: ['GET', 'POST'])]
    public function adjustOffer(Request $request, string $id): Response
    {
        $order = $this->orderRepository->find($id);
        if (!$order instanceof Order) {
            throw $this->createNotFoundException();
        }

        if (!$this->canAdjust($order)) {
            $this->addFlash('sonata_flash_error', $this->translator->trans('order_offer.adjust.error.not_adjustable', [], 'AppBundle'));

            return $this->redirectToOrderShow($order);
        }

        $latestOffer = $order->getLatestOffer();
        \assert($latestOffer instanceof OrderOffer);
        $breakdown = $this->senderOrderPayableTotalCentsCalculator->buildBreakdown($order, $latestOffer);
        $currentTotalCents = $breakdown?->senderTotalGrossCents ?? 0;
        $currency = $order->getCurrency() ?? 'EUR';

        $form = $this->createForm(OfferPriceAdjustmentType::class, [
            'mode' => OfferPriceAdjustmentMode::TARGET_TOTAL,
            'numericValue' => round($currentTotalCents / 100, 2),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{mode: OfferPriceAdjustmentMode, numericValue: numeric-string|float|int, reason: string} $data */
            $data = $form->getData();
            $manager = $this->getUser();
            $result = $this->adjustmentService->adjust(
                $order,
                new OfferPriceAdjustmentInput(
                    mode: $data['mode'],
                    numericValue: (float) $data['numericValue'],
                    reason: trim($data['reason']),
                ),
                $manager instanceof Manager ? $manager : null,
            );

            if (!$result->success) {
                $this->addFlash(
                    'sonata_flash_error',
                    $this->translator->trans($result->errorMessage ?? 'order_offer.adjust.error.unknown', [], 'AppBundle'),
                );

                return $this->redirectToOrderShow($order);
            }

            $this->addFlash(
                'sonata_flash_success',
                $this->translator->trans('order_offer.adjust.success', [
                    '%previous%' => number_format(($result->previousSenderTotalCents ?? 0) / 100, 2, '.', ''),
                    '%new%' => number_format(($result->newSenderTotalCents ?? 0) / 100, 2, '.', ''),
                    '%currency%' => $currency,
                ], 'AppBundle'),
            );

            return $this->redirectToOrderShow($order);
        }

        return $this->render('admin/order/adjust_offer.html.twig', [
            'order' => $order,
            'offer' => $latestOffer,
            'breakdown' => $breakdown,
            'currency' => $currency,
            'form' => $form->createView(),
            'orderAdmin' => $this->adminPool->getAdminByAdminCode('App\Admin\OrderAdmin'),
        ]);
    }

    private function canAdjust(Order $order): bool
    {
        if ($order->getStatus() !== Order::STATUS['OFFERED']) {
            return false;
        }

        $latestOffer = $order->getLatestOffer();

        return $latestOffer instanceof OrderOffer
            && $latestOffer->getStatus() === OrderOffer::STATUS['DRAFT'];
    }

    private function redirectToOrderShow(Order $order): Response
    {
        $admin = $this->adminPool->getAdminByAdminCode('App\Admin\OrderAdmin');

        return $this->redirect($admin->generateObjectUrl('show', $order));
    }
}

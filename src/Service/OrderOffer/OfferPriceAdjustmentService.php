<?php

declare(strict_types=1);

namespace App\Service\OrderOffer;

use App\Entity\Manager;
use App\Entity\Order;
use App\Entity\OrderHistory;
use App\Entity\OrderOffer;
use App\Enum\OfferPriceAdjustmentMode;
use App\Enum\OfferPricingSource;
use App\Service\Order\SenderOrderPayableTotalCentsCalculator;
use App\Service\OrderOffer\DTO\OfferPriceAdjustmentInput;
use App\Service\OrderOffer\DTO\OfferPriceAdjustmentResult;
use App\Service\OrderOffer\Pricing\PricingAmountBuilder;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Replaces the active DRAFT offer with a new DRAFT offer at an adjusted sender-facing total.
 */
final class OfferPriceAdjustmentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SenderOrderPayableTotalCentsCalculator $senderOrderPayableTotalCentsCalculator,
        private readonly PricingAmountBuilder $pricingAmountBuilder,
    ) {
    }

    public function adjust(Order $order, OfferPriceAdjustmentInput $input, ?Manager $manager): OfferPriceAdjustmentResult
    {
        $reason = trim($input->reason);
        if ($reason === '' || mb_strlen($reason) < 3) {
            return OfferPriceAdjustmentResult::error(
                'order_offer.adjust.error.reason_required',
                'REASON_REQUIRED',
            );
        }

        if ($order->getStatus() !== Order::STATUS['OFFERED']) {
            return OfferPriceAdjustmentResult::error(
                'order_offer.adjust.error.order_not_offered',
                'ORDER_NOT_OFFERED',
            );
        }

        $currentOffer = $order->getLatestOffer();
        if (!$currentOffer instanceof OrderOffer) {
            return OfferPriceAdjustmentResult::error(
                'order_offer.adjust.error.no_active_offer',
                'NO_ACTIVE_OFFER',
            );
        }

        if ($currentOffer->getStatus() !== OrderOffer::STATUS['DRAFT']) {
            return OfferPriceAdjustmentResult::error(
                'order_offer.adjust.error.offer_not_draft',
                'OFFER_NOT_DRAFT',
            );
        }

        $breakdown = $this->senderOrderPayableTotalCentsCalculator->buildBreakdown($order, $currentOffer);
        if ($breakdown === null) {
            return OfferPriceAdjustmentResult::error(
                'order_offer.adjust.error.invalid_current_offer',
                'INVALID_CURRENT_OFFER',
            );
        }

        $currentSenderTotal = $breakdown->senderTotalGrossCents;
        $targetSenderTotal = $this->resolveTargetSenderTotalCents($input, $currentSenderTotal);
        if ($targetSenderTotal < 1) {
            return OfferPriceAdjustmentResult::error(
                'order_offer.adjust.error.invalid_target_total',
                'INVALID_TARGET_TOTAL',
            );
        }

        $baseFreightCents = $this->findBaseFreightForSenderTotal(
            $order,
            $targetSenderTotal,
            $breakdown->freightNetCents,
        );
        $amounts = $this->pricingAmountBuilder->buildFromBaseFreightCents($baseFreightCents);

        $newOffer = new OrderOffer();
        $newOffer->setRelatedOrder($order);
        $newOffer->setNetto($amounts->nettoCents);
        $newOffer->setFee($amounts->feeCents);
        $newOffer->setVat($amounts->vatCents);
        $newOffer->setBrutto($amounts->bruttoCents);
        $newOffer->setStatus(OrderOffer::STATUS['DRAFT']);
        $newOffer->setPricingSource(OfferPricingSource::MANUAL);
        $newOffer->setAdjustmentReason($reason);
        $newOffer->setAdjustedByManager($manager);

        $currentOffer->setStatus(OrderOffer::STATUS['REJECTED']);
        $currentOffer->setSupersededBy($newOffer);

        $order->addOffer($newOffer);

        $newSenderTotal = $this->senderOrderPayableTotalCentsCalculator
            ->computePayableGrossCents($newOffer, $order) ?? $targetSenderTotal;

        $this->entityManager->persist($newOffer);
        $this->entityManager->flush();

        $history = new OrderHistory();
        $history->setRelatedOrder($order);
        $history->setStatus(Order::STATUS['OFFERED']);
        $history->setChangedBy(OrderHistory::CHANGED_BY['MANUAL']);
        $history->setMeta([
            'event' => 'offer_price_adjusted',
            'previous_offer_id' => $currentOffer->getId()?->toRfc4122(),
            'new_offer_id' => $newOffer->getId()?->toRfc4122(),
            'previous_sender_total_cents' => $currentSenderTotal,
            'new_sender_total_cents' => $newSenderTotal,
            'adjustment_mode' => $input->mode->value,
            'adjustment_value' => $input->numericValue,
            'reason' => $reason,
            'adjusted_by_manager_id' => $manager?->getId()?->toRfc4122(),
        ]);
        $order->addHistory($history);

        $this->entityManager->persist($history);
        $this->entityManager->flush();

        return OfferPriceAdjustmentResult::success(
            $newOffer,
            $currentOffer,
            $currentSenderTotal,
            $newSenderTotal,
        );
    }

    private function resolveTargetSenderTotalCents(OfferPriceAdjustmentInput $input, int $currentSenderTotal): int
    {
        return match ($input->mode) {
            OfferPriceAdjustmentMode::TARGET_TOTAL => (int) round($input->numericValue * 100),
            OfferPriceAdjustmentMode::PERCENT => (int) round($currentSenderTotal * (1 + $input->numericValue / 100)),
            OfferPriceAdjustmentMode::DELTA => $currentSenderTotal + (int) round($input->numericValue * 100),
        };
    }

    private function findBaseFreightForSenderTotal(Order $order, int $targetSenderTotalCents, int $hintFreightNet): int
    {
        $low = 1;
        $high = max($hintFreightNet * 4, $targetSenderTotalCents * 2, 100);

        while ($this->senderTotalForBaseFreight($order, $high) < $targetSenderTotalCents && $high < 50_000_000) {
            $high *= 2;
        }

        while ($low < $high) {
            $mid = intdiv($low + $high, 2);
            if ($this->senderTotalForBaseFreight($order, $mid) < $targetSenderTotalCents) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        $bestFreight = $low;
        $bestDiff = abs($this->senderTotalForBaseFreight($order, $low) - $targetSenderTotalCents);

        foreach ([$low - 1, $low, $low + 1] as $candidate) {
            if ($candidate < 1) {
                continue;
            }
            $diff = abs($this->senderTotalForBaseFreight($order, $candidate) - $targetSenderTotalCents);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $bestFreight = $candidate;
            }
        }

        return $bestFreight;
    }

    private function senderTotalForBaseFreight(Order $order, int $baseFreightCents): int
    {
        $amounts = $this->pricingAmountBuilder->buildFromBaseFreightCents($baseFreightCents);
        $probe = (new OrderOffer())
            ->setNetto($amounts->nettoCents)
            ->setFee($amounts->feeCents)
            ->setVat($amounts->vatCents)
            ->setBrutto($amounts->bruttoCents);

        return $this->senderOrderPayableTotalCentsCalculator->computePayableGrossCents($probe, $order) ?? PHP_INT_MAX;
    }
}

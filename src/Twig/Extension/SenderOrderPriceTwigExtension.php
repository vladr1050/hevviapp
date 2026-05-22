<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Entity\Order;
use App\Entity\OrderOffer;
use App\Service\Order\DTO\SenderOrderPriceBreakdown;
use App\Service\Order\SenderOrderPayableTotalCentsCalculator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SenderOrderPriceTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly SenderOrderPayableTotalCentsCalculator $senderOrderPayableTotalCentsCalculator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sender_order_price_breakdown', $this->buildBreakdown(...)),
            new TwigFunction('sender_delivery_gross_cents', $this->senderDeliveryGrossCents(...)),
        ];
    }

    public function buildBreakdown(?Order $order, ?OrderOffer $offer = null): ?SenderOrderPriceBreakdown
    {
        if ($order === null) {
            return null;
        }

        return $this->senderOrderPayableTotalCentsCalculator->buildBreakdown(
            $order,
            $offer ?? $order->getLatestOffer(),
        );
    }

    public function senderDeliveryGrossCents(?Order $order, ?OrderOffer $offer = null): ?int
    {
        if ($order === null) {
            return null;
        }

        return $this->senderOrderPayableTotalCentsCalculator->computePayableGrossCents(
            $offer ?? $order->getLatestOffer(),
            $order,
        );
    }
}

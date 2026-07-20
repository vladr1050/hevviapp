<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Service\Order\PortalOngoingOrdersService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PortalOngoingOrdersTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly PortalOngoingOrdersService $portalOngoingOrdersService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('portal_ongoing_orders_widget', $this->portalOngoingOrdersService->buildForCurrentUser(...)),
        ];
    }
}

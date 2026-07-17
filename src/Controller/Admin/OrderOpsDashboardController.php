<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Service\Order\OrderOpsDashboardService;
use Sonata\AdminBundle\Admin\Pool;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ops', name: 'admin_ops_')]
#[IsGranted('ROLE_ADMIN')]
final class OrderOpsDashboardController extends AbstractController
{
    public function __construct(
        private readonly OrderOpsDashboardService $opsDashboardService,
        private readonly Pool $adminPool,
    ) {
    }

    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $orderAdmin = $this->adminPool->getAdminByAdminCode('App\\Admin\\OrderAdmin');
        $notificationLogAdmin = $this->adminPool->getAdminByAdminCode('App\\Admin\\NotificationLogAdmin');

        return $this->render('admin/ops/dashboard.html.twig', [
            'data' => $this->opsDashboardService->build(),
            'orderAdmin' => $orderAdmin,
            'notificationLogAdmin' => $notificationLogAdmin,
            'statusLabels' => array_flip(Order::STATUS),
            'statusOrder' => Order::STATUS,
        ]);
    }
}

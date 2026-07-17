<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Service\Order\OrderOpsDashboardService;
use Sonata\AdminBundle\Admin\Pool;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ops', name: 'admin_ops_')]
#[IsGranted('ROLE_ADMIN')]
final class OrderOpsDashboardController extends AbstractController
{
    private const string APP_TZ = 'Europe/Riga';

    public function __construct(
        private readonly OrderOpsDashboardService $opsDashboardService,
        private readonly Pool $adminPool,
    ) {
    }

    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        $isTest = $request->query->getBoolean('isTest');
        $tz = new \DateTimeZone(self::APP_TZ);
        $today = (new \DateTimeImmutable('now', $tz))->setTime(0, 0);

        $from = $this->parseDateQuery($request->query->getString('from'), $tz) ?? $today;
        $to = $this->parseDateQuery($request->query->getString('to'), $tz) ?? $today;

        $data = $this->opsDashboardService->build($isTest, $from, $to);

        $orderAdmin = $this->adminPool->getAdminByAdminCode('App\\Admin\\OrderAdmin');
        $notificationLogAdmin = $this->adminPool->getAdminByAdminCode('App\\Admin\\NotificationLogAdmin');

        $fromStr = $data->periodFrom->format('Y-m-d');
        $toStr = $data->periodToInclusive()->format('Y-m-d');

        return $this->render('admin/ops/dashboard.html.twig', [
            'data' => $data,
            'isTest' => $isTest,
            // Sonata BooleanFilter: TYPE_YES=1, TYPE_NO=2
            'isTestFilterValue' => $isTest ? 1 : 2,
            'from' => $fromStr,
            'to' => $toStr,
            'filterQuery' => [
                'isTest' => $isTest ? 1 : 0,
                'from' => $fromStr,
                'to' => $toStr,
            ],
            'presets' => $this->buildPresets($today, $isTest),
            'orderAdmin' => $orderAdmin,
            'notificationLogAdmin' => $notificationLogAdmin,
            'statusLabels' => array_flip(Order::STATUS),
            'statusOrder' => Order::STATUS,
        ]);
    }

    private function parseDateQuery(string $value, \DateTimeZone $tz): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $tz);
        if ($parsed === false) {
            return null;
        }

        $errors = \DateTimeImmutable::getLastErrors();
        if (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
            return null;
        }

        return $parsed->setTime(0, 0);
    }

    /**
     * @return list<array{label_key: string, from: string, to: string, isTest: int}>
     */
    private function buildPresets(\DateTimeImmutable $today, bool $isTest): array
    {
        $flag = $isTest ? 1 : 0;

        return [
            [
                'label_key' => 'ops_dashboard.period.today',
                'from' => $today->format('Y-m-d'),
                'to' => $today->format('Y-m-d'),
                'isTest' => $flag,
            ],
            [
                'label_key' => 'ops_dashboard.period.last_7_days',
                'from' => $today->modify('-6 days')->format('Y-m-d'),
                'to' => $today->format('Y-m-d'),
                'isTest' => $flag,
            ],
            [
                'label_key' => 'ops_dashboard.period.this_month',
                'from' => $today->modify('first day of this month')->format('Y-m-d'),
                'to' => $today->format('Y-m-d'),
                'isTest' => $flag,
            ],
            [
                'label_key' => 'ops_dashboard.period.this_year',
                'from' => $today->setDate((int) $today->format('Y'), 1, 1)->format('Y-m-d'),
                'to' => $today->format('Y-m-d'),
                'isTest' => $flag,
            ],
        ];
    }
}

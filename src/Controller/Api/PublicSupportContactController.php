<?php

/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Controller\Api;

use App\Repository\ManagerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public support phone/email for the SPA help popover (no secrets).
 */
final class PublicSupportContactController extends AbstractController
{
    public function __construct(
        private readonly ManagerRepository $managerRepository,
        private readonly string $supportManagerEmail,
    ) {
    }

    #[Route('/public/support-contact', name: 'api_public_support_contact', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $manager = $this->managerRepository->findOneBy(['email' => $this->supportManagerEmail]);

        if ($manager === null || !$manager->isAccountIsActive()) {
            return $this->json([
                'email' => null,
                'phone' => null,
            ]);
        }

        return $this->json([
            'email' => $manager->getEmail(),
            'phone' => $manager->getPhoneNumber(),
        ]);
    }
}

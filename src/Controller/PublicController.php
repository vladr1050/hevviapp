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

namespace App\Controller;

use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Базовый контроллер публичной части приложения.
 *
 * Отдаёт SPA-оболочку, внутри которой React монтирует острова (island architecture)
 * через data-react-island атрибуты.
 */
class PublicController extends AbstractController
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
    ) {
    }

    #[Route('/', name: 'public_index')]
    public function index(): Response
    {
        return $this->render('public/user/pages/login.html.twig');
    }

     #[Route('/login', name: 'public_login')]
    public function login(): Response
    {
        return $this->render('public/user/pages/login.html.twig', [
            'title' => 'Dashboard',
        ]);
    }

     #[Route('/registration', name: 'public_registration')]
    public function registration(): Response
    {
        return $this->render('public/user/pages/registration.html.twig', [
            'title' => 'Dashboard',
        ]);
    }

    #[Route('/logout', name: 'public_logout', methods: ['GET'])]
    public function logout(): Response
    {
        $user = $this->getUser();

        if ($user instanceof User) {
            $this->refreshTokenRepository->deleteAllForUser((string) $user->getId());
        }

        $response = $this->render('public/logout.html.twig');

        $response->headers->setCookie(
            Cookie::create('BEARER')
                ->withValue('')
                ->withExpires(1)
                ->withPath('/')
                ->withSameSite('lax')
        );

        return $response;
    }
}

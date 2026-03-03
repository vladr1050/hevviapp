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

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    #[Route('/', name: 'public_index')]
    public function index(): Response
    {
        return $this->render('public/user/pages/requests.html.twig');
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
}

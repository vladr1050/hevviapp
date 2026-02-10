<?php
/*
 * DOO TECHGURU Confidential
 * Copyright (C) 2022 DOO TECHGURU.
 * All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains
 * the property of DOO TECHGURU, its suppliers and Customers,
 * if any.  The intellectual and technical concepts contained
 * herein are proprietary to DOO TECHGURU
 * its Suppliers and Customers are protected by trade secret or copyright law.
 *
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained.
 */

namespace FRPC\SonataAuthorization\Controller;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use FRPC\SonataAuthorization\Form\AdminLoginForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;

class AdminSecurityController extends AbstractController
{
    public function __construct(
        protected FormFactoryInterface $formFactory,
        protected Environment $twig
    ) {
    }

    #[Route(path: "/admin/login", name: "_user_admin_security_login", options: [
        'expose' > true,
    ])]
    public function loginAction(AuthenticationUtils $authenticationUtils, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $form = $this->formFactory->create(AdminLoginForm::class, [
            'email' => $authenticationUtils->getLastUsername(),
        ]);

        return new Response($this->twig->render('@SonataAuthorization/Admin/Security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'form' => $form->createView(),
            'csrf_token' => $csrfTokenManager->getToken('authenticate')->getValue(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]));
    }

    #[Route(path: "/admin/login_check", name: "_user_admin_security_login_check", methods: [
        'POST',
    ])]
    public function checkAction(): void
    {
        throw new \RuntimeException('You must configure the check path to be handled by the firewall using form_login in your security firewall configuration.');
    }

    #[Route(path: "/admin/logout", name: "_user_admin_security_logout")]
    public function logoutAction(): void
    {
        throw new \RuntimeException('You must activate the logout in your security firewall configuration.');
    }
}
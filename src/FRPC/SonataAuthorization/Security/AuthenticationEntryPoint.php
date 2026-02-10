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

namespace FRPC\SonataAuthorization\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class AuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function start(Request $request, AuthenticationException $authException = null): RedirectResponse
    {
        // add a custom flash message and redirect to the login page
        $request->getSession()->getFlashBag()->add('note', 'You must be authorized to access this page.');

        return new RedirectResponse($this->urlGenerator->generate('_user_admin_security_login'));
    }
}
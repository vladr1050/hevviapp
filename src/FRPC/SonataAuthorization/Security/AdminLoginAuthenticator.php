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

use App\Entity\Manager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AdminLoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = '_user_admin_security_login';

    public function __construct(
        protected EntityManagerInterface $em,
        protected UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->attributes->get('_route') === '_user_admin_security_login' && $request->isMethod('POST');
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): RedirectResponse
    {
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($this->urlGenerator->generate('_user_admin_security_login'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): RedirectResponse {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('sonata_admin_dashboard'));
    }

    public function authenticate(Request $request): Passport
    {
        $loginForm = $request->request->all('admin_login_form');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $loginForm['email']);

        return new Passport(
            new UserBadge($loginForm['email']),
            new PasswordCredentials($loginForm['password']),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
            ]
        );
    }
}
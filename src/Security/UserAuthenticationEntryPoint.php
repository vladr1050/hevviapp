<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class UserAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(protected readonly RouterInterface $router)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if ($this->wantsJsonResponse($request)) {
            return new JsonResponse(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse($this->router->generate('public_login'));
    }

    private function wantsJsonResponse(Request $request): bool
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return true;
        }

        if ($request->isXmlHttpRequest()) {
            return true;
        }

        $accept = $request->headers->get('Accept', '');

        return str_contains($accept, 'application/json');
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Enum\WaitingListApplicantType;
use App\Service\WaitingList\WaitingListEmailExistsException;
use App\Service\WaitingList\WaitingListRateLimiter;
use App\Service\WaitingList\WaitingListRegistrar;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicWaitingListController extends AbstractController
{
    public function __construct(
        private readonly WaitingListRegistrar $registrar,
        private readonly WaitingListRateLimiter $rateLimiter,
    ) {
    }

    #[Route('/public/waiting-list', name: 'api_public_waiting_list', methods: ['POST'])]
    public function join(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        // Honeypot: bots that fill hidden fields get a silent OK.
        $honeypot = trim((string) ($data['company_website'] ?? ''));
        if ($honeypot !== '') {
            return $this->json(['success' => true]);
        }

        $clientIp = $request->getClientIp() ?? 'unknown';
        if ($this->rateLimiter->isLimited($clientIp)) {
            return $this->json(
                ['error' => 'Too many requests. Please try again later.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $email = trim((string) ($data['email'] ?? ''));
        $type = WaitingListApplicantType::tryFromRequest((string) ($data['type'] ?? ''));
        if ($type === null) {
            return $this->json(
                ['error' => 'Field "type" must be "sender" or "carrier".'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'A valid email address is required.'], Response::HTTP_BAD_REQUEST);
        }

        $this->rateLimiter->recordAttempt($clientIp);

        try {
            $this->registrar->register($email, $type);
        } catch (WaitingListEmailExistsException) {
            return $this->json(
                ['error' => 'A user with this email already exists.', 'code' => 'EMAIL_EXISTS'],
                Response::HTTP_CONFLICT,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['success' => true]);
    }
}

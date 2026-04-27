<?php

namespace App\Controller\Api;

use App\Entity\Carrier;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Enum\TermsAudience;
use App\Repository\CarrierRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\TermsOfUseRevisionRepository;
use App\Repository\UserRepository;
use App\Service\PortalLoginConsentLogService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    private const REFRESH_TOKEN_TTL_DAYS = 90;
    private const ACCESS_TOKEN_TTL_SECONDS = 86400;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CarrierRepository $carrierRepository,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly TermsOfUseRevisionRepository $termsOfUseRevisionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PortalLoginConsentLogService $portalLoginConsentLogService,
    ) {
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['login'], $data['password'])) {
            return $this->json(['error' => 'Missing credentials'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!isset($data['terms_accepted']) || $data['terms_accepted'] !== true) {
            return $this->json(
                ['error' => 'Terms must be accepted before login.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $audienceRaw = strtolower(trim((string) ($data['portal_audience'] ?? '')));
        $portalAudience = match ($audienceRaw) {
            'sender' => TermsAudience::Sender,
            'carrier' => TermsAudience::Carrier,
            default => null,
        };

        if ($portalAudience === null) {
            return $this->json(
                ['error' => 'Field "portal_audience" must be "sender" or "carrier".'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $subject = $this->userRepository->findOneBy(['email' => $data['login']]);
        $accountType = 'user';

        if (!$subject instanceof User) {
            $subject = $this->carrierRepository->findOneBy(['email' => $data['login']]);
            $accountType = 'carrier';
        }

        if (!$subject instanceof UserInterface) {
            return $this->json(['error' => 'Invalid credentials'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if (!$this->passwordHasher->isPasswordValid($subject, $data['password'])) {
            return $this->json(['error' => 'Invalid credentials'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if (!$this->isSubjectEnabled($subject)) {
            return $this->json(['error' => 'Account is disabled'], JsonResponse::HTTP_FORBIDDEN);
        }

        $accessToken = $this->jwtManager->create($subject);
        $refreshToken = $this->createRefreshToken($subject);

        $termsRevision = $this->termsOfUseRevisionRepository->findCurrentPublished($portalAudience);
        try {
            $this->portalLoginConsentLogService->recordSuccessfulLogin(
                $request,
                $subject,
                $accountType,
                $portalAudience,
                $termsRevision,
            );
        } catch (\Throwable $e) {
            error_log('portal_login_consent_log: '.$e->getMessage());
        }

        return $this->json([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken->getToken(),
            'expires_in'    => self::ACCESS_TOKEN_TTL_SECONDS,
            'token_type'    => 'Bearer',
            'account_type'  => $accountType,
            'user'          => $this->serializeSubject($subject),
        ]);
    }

    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['refresh_token'])) {
            return $this->json(['error' => 'Missing refresh_token'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $refreshToken = $this->refreshTokenRepository->findValidToken($data['refresh_token']);

        if (!$refreshToken) {
            return $this->json(['error' => 'Invalid or expired refresh token'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $subject = $refreshToken->getSubject();
        $accessToken = $this->jwtManager->create($subject);

        $newRefreshToken = $this->createRefreshToken($subject);
        $this->entityManager->remove($refreshToken);
        $this->entityManager->flush();

        return $this->json([
            'access_token'  => $accessToken,
            'refresh_token' => $newRefreshToken->getToken(),
            'expires_in'    => self::ACCESS_TOKEN_TTL_SECONDS,
            'token_type'    => 'Bearer',
        ]);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $subject = $this->getUser();

        if (!$subject instanceof User && !$subject instanceof Carrier) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'user'         => $this->serializeSubject($subject),
            'account_type' => $subject instanceof Carrier ? 'carrier' : 'user',
        ]);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['refresh_token'])) {
            $refreshToken = $this->refreshTokenRepository->findValidToken($data['refresh_token']);
            if ($refreshToken) {
                $this->entityManager->remove($refreshToken);
                $this->entityManager->flush();
            }
        }

        return $this->json(['success' => true]);
    }

    private function createRefreshToken(UserInterface $subject): RefreshToken
    {
        $token = bin2hex(random_bytes(64));
        $expiresAt = new \DateTimeImmutable(sprintf('+%d days', self::REFRESH_TOKEN_TTL_DAYS));

        $refreshToken = new RefreshToken($subject, $token, $expiresAt);

        $this->entityManager->persist($refreshToken);
        $this->entityManager->flush();

        return $refreshToken;
    }

    private function isSubjectEnabled(UserInterface $subject): bool
    {
        if ($subject instanceof User) {
            return ($subject->getState() & 4) === 4;
        }

        if ($subject instanceof Carrier) {
            return ($subject->getState() & 4) === 4;
        }

        return false;
    }

    private function serializeSubject(UserInterface $subject): array
    {
        if ($subject instanceof User) {
            return [
                'id'         => (string) $subject->getId(),
                'email'      => $subject->getUserIdentifier(),
                'firstName'  => $subject->getFirstName(),
                'lastName'   => $subject->getLastName(),
                'phone'      => $subject->getPhone(),
                'locale'     => $subject->getLocale(),
                'roles'      => $subject->getRoles(),
            ];
        }

        if ($subject instanceof Carrier) {
            return [
                'id'         => (string) $subject->getId(),
                'email'      => $subject->getUserIdentifier(),
                'firstName'  => $subject->getFirstName(),
                'lastName'   => $subject->getLastName(),
                'phone'      => $subject->getPhone(),
                'locale'     => $subject->getLocale(),
                'roles'      => $subject->getRoles(),
            ];
        }

        return [];
    }
}

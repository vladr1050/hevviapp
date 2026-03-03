<?php

namespace App\Controller\Api;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

#[Route('/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    private const REFRESH_TOKEN_TTL_DAYS = 90;
    private const ACCESS_TOKEN_TTL_SECONDS = 86400;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['login'], $data['password'])) {
            return $this->json(['error' => 'Missing credentials'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $data['login']]);

        if (!$user instanceof User) {
            return $this->json(['error' => 'Invalid credentials'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            return $this->json(['error' => 'Invalid credentials'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if (!$this->isUserEnabled($user)) {
            return $this->json(['error' => 'Account is disabled'], JsonResponse::HTTP_FORBIDDEN);
        }

        $accessToken = $this->jwtManager->create($user);
        $refreshToken = $this->createRefreshToken($user);

        return $this->json([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken->getToken(),
            'expires_in'    => self::ACCESS_TOKEN_TTL_SECONDS,
            'token_type'    => 'Bearer',
            'user'          => $this->serializeUser($user),
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

        $user = $refreshToken->getUser();
        $accessToken = $this->jwtManager->create($user);

        $newRefreshToken = $this->createRefreshToken($user);
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
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->json(['user' => $this->serializeUser($user)]);
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

    private function createRefreshToken(User $user): RefreshToken
    {
        $token = bin2hex(random_bytes(64));
        $expiresAt = new \DateTimeImmutable(sprintf('+%d days', self::REFRESH_TOKEN_TTL_DAYS));

        $refreshToken = new RefreshToken($user, $token, $expiresAt);

        $this->entityManager->persist($refreshToken);
        $this->entityManager->flush();

        return $refreshToken;
    }

    private function isUserEnabled(User $user): bool
    {
        return ($user->getState() & 4) === 4;
    }

    private function serializeUser(User $user): array
    {
        return [
            'id'         => (string) $user->getId(),
            'email'      => $user->getUserIdentifier(),
            'firstName'  => $user->getFirstName(),
            'lastName'   => $user->getLastName(),
            'phone'      => $user->getPhone(),
            'locale'     => $user->getLocale(),
            'roles'      => $user->getRoles(),
        ];
    }
}

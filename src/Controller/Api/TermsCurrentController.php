<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Carrier;
use App\Entity\User;
use App\Enum\TermsAudience;
use App\Repository\TermsOfUseRevisionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Current published terms for the logged-in portal user (JWT on /api firewall).
 */
final class TermsCurrentController extends AbstractController
{
    public function __construct(
        private readonly TermsOfUseRevisionRepository $termsOfUseRevisionRepository,
    ) {
    }

    #[Route('/terms/current', name: 'api_terms_current', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $subject = $this->getUser();
        $audience = match (true) {
            $subject instanceof Carrier => TermsAudience::Carrier,
            $subject instanceof User    => TermsAudience::Sender,
            default                     => null,
        };

        if ($audience === null) {
            return $this->json(['error' => 'Unsupported account'], Response::HTTP_FORBIDDEN);
        }

        $revision = $this->termsOfUseRevisionRepository->findCurrentPublished($audience);
        if ($revision === null) {
            return $this->json(['error' => 'No published terms for this account type'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'audience'    => $revision->getAudience()->value,
            'version'     => $revision->getVersion(),
            'title'       => $revision->getTitle(),
            'subtitle'    => $revision->getSubtitle(),
            'publishedAt' => $revision->getPublishedAt()?->format(\DateTimeInterface::ATOM),
            'html'        => $revision->getBodyHtml(),
        ]);
    }
}

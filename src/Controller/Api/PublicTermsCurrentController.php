<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Enum\TermsAudience;
use App\Repository\TermsOfUseRevisionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Published terms for login / registration (no JWT). Audience is explicit query param.
 */
final class PublicTermsCurrentController extends AbstractController
{
    public function __construct(
        private readonly TermsOfUseRevisionRepository $termsOfUseRevisionRepository,
    ) {
    }

    #[Route('/public/terms/current', name: 'api_public_terms_current', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $raw = strtolower(trim((string) $request->query->get('audience', '')));
        $audience = match ($raw) {
            'sender' => TermsAudience::Sender,
            'carrier' => TermsAudience::Carrier,
            default => null,
        };

        if ($audience === null) {
            return $this->json(
                ['error' => 'Query "audience" must be "sender" or "carrier".'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $revision = $this->termsOfUseRevisionRepository->findCurrentPublished($audience);
        if ($revision === null) {
            return $this->json(['error' => 'No published terms for this audience'], Response::HTTP_NOT_FOUND);
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

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TermsOfUseRevision;
use App\Enum\TermsRevisionStatus;
use App\Repository\TermsOfUseRevisionRepository;

/**
 * Keeps a single {@see TermsRevisionStatus::Published} row per audience; older published rows become superseded.
 */
final class TermsOfUsePublishService
{
    public function __construct(
        private readonly TermsOfUseRevisionRepository $termsOfUseRevisionRepository,
    ) {
    }

    /**
     * Call from admin prePersist / preUpdate before flush.
     */
    public function applyPublicationRules(TermsOfUseRevision $revision): void
    {
        if ($revision->getStatus() !== TermsRevisionStatus::Published) {
            return;
        }

        $id = $revision->getId();
        if ($id !== null) {
            $this->termsOfUseRevisionRepository->supersedeOtherPublished($revision->getAudience(), $id);
        }

        if ($revision->getPublishedAt() === null) {
            $revision->setPublishedAt(new \DateTimeImmutable());
        }
    }
}

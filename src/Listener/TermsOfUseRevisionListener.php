<?php

declare(strict_types=1);

namespace App\Listener;

use App\Entity\TermsOfUseRevision;
use App\Repository\TermsOfUseRevisionRepository;
use App\Service\TermsOfUsePublishService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Assigns monotonic version on create; applies single-published rules when status is published.
 */
#[AsEntityListener(event: Events::prePersist, entity: TermsOfUseRevision::class)]
#[AsEntityListener(event: Events::preUpdate, entity: TermsOfUseRevision::class)]
final class TermsOfUseRevisionListener
{
    public function __construct(
        private readonly TermsOfUseRevisionRepository $termsOfUseRevisionRepository,
        private readonly TermsOfUsePublishService $termsOfUsePublishService,
    ) {
    }

    public function prePersist(TermsOfUseRevision $entity, PrePersistEventArgs $args): void
    {
        $entity->setVersion($this->termsOfUseRevisionRepository->getNextVersion($entity->getAudience()));
        $this->termsOfUsePublishService->applyPublicationRules($entity);
    }

    public function preUpdate(TermsOfUseRevision $entity, PreUpdateEventArgs $args): void
    {
        $this->termsOfUsePublishService->applyPublicationRules($entity);
    }
}

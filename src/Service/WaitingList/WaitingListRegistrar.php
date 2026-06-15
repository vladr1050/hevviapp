<?php

declare(strict_types=1);

namespace App\Service\WaitingList;

use App\Entity\WaitingListApplicant;
use App\Enum\WaitingListApplicantType;
use App\Notification\NotificationEventKey;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final class WaitingListRegistrar
{
    public function __construct(
        private readonly WaitingListEmailUniquenessChecker $emailUniquenessChecker,
        private readonly WaitingListContextFactory $contextFactory,
        private readonly WaitingListNotificationDispatcher $notificationDispatcher,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @throws WaitingListEmailExistsException
     */
    public function register(string $email, WaitingListApplicantType $type): WaitingListApplicant
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        if ($this->emailUniquenessChecker->isEmailTaken($normalized)) {
            throw new WaitingListEmailExistsException();
        }

        $applicant = new WaitingListApplicant();
        $applicant->setEmail($normalized);
        $applicant->setType($type);

        try {
            $this->em->persist($applicant);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new WaitingListEmailExistsException();
        }

        $variables = $this->contextFactory->build($applicant);

        $this->notificationDispatcher->dispatch(
            NotificationEventKey::WAITING_LIST_CONFIRMATION,
            $variables,
            $normalized,
        );
        $this->notificationDispatcher->dispatch(
            NotificationEventKey::WAITING_LIST_NEW_APPLICATION,
            $variables,
            $normalized,
        );

        return $applicant;
    }
}

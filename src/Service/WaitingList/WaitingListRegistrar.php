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
    public function register(string $email, WaitingListApplicantType $type, string $phone): WaitingListApplicant
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        $normalizedPhone = $this->normalizePhone($phone);
        if ($normalizedPhone === '') {
            throw new \InvalidArgumentException('A valid phone number is required.');
        }

        if ($this->emailUniquenessChecker->isEmailTaken($normalized)) {
            throw new WaitingListEmailExistsException();
        }

        $applicant = new WaitingListApplicant();
        $applicant->setEmail($normalized);
        $applicant->setPhone($normalizedPhone);
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

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';
        if ($digits === '' || strlen($digits) < 8) {
            return '';
        }

        return $digits;
    }
}

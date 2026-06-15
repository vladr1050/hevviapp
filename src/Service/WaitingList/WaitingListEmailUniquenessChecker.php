<?php

declare(strict_types=1);

namespace App\Service\WaitingList;

use App\Repository\CarrierRepository;
use App\Repository\UserRepository;
use App\Repository\WaitingListApplicantRepository;

final class WaitingListEmailUniquenessChecker
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CarrierRepository $carrierRepository,
        private readonly WaitingListApplicantRepository $waitingListApplicantRepository,
    ) {
    }

    public function isEmailTaken(string $email): bool
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return false;
        }

        if ($this->userRepository->findOneBy(['email' => $normalized]) !== null) {
            return true;
        }

        if ($this->carrierRepository->findOneBy(['email' => $normalized]) !== null) {
            return true;
        }

        return $this->waitingListApplicantRepository->findOneByNormalizedEmail($normalized) !== null;
    }
}

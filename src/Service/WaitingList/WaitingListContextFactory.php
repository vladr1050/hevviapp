<?php

declare(strict_types=1);

namespace App\Service\WaitingList;

use App\Entity\WaitingListApplicant;
use App\Repository\BillingCompanyRepository;

/**
 * Placeholders for waiting-list notification templates ({{KEY}} syntax).
 */
final class WaitingListContextFactory
{
    public function __construct(
        private readonly BillingCompanyRepository $billingCompanyRepository,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function build(WaitingListApplicant $applicant): array
    {
        $issuer = $this->billingCompanyRepository->findIssuingCompany();

        return [
            'APPLICANT_EMAIL' => $applicant->getEmail(),
            'APPLICANT_PHONE' => $applicant->getPhone(),
            'APPLICANT_TYPE' => $applicant->getType()->labelLv(),
            'OPERATOR_NAME' => trim((string) ($issuer?->getName() ?? '')),
            'OPERATOR_PHONE' => trim((string) ($issuer?->getPhone() ?? '')),
            'OPERATOR_EMAIL' => trim((string) ($issuer?->getEmail() ?? '')),
        ];
    }
}

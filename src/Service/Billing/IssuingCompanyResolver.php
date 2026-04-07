<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Service\Billing;

use App\Entity\BillingCompany;
use App\Repository\BillingCompanyRepository;

/**
 * Resolves the company whose requisites and VAT rate apply to issued invoices.
 */
final class IssuingCompanyResolver
{
    public function __construct(
        private readonly BillingCompanyRepository $billingCompanyRepository,
    ) {
    }

    public function getIssuingCompany(): ?BillingCompany
    {
        return $this->billingCompanyRepository->findIssuingCompany();
    }
}

<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Entity;

use App\Repository\BillingCompanyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BillingCompanyRepository::class)]
#[ORM\Table(name: 'billing_company')]
class BillingCompany extends BaseUUID
{
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $registrationNumber = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $vatNumber = null;

    /** VAT rate in percent (e.g. 21 for 21%), used for invoice calculations when this company issues invoices. */
    #[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 4)]
    #[Assert\NotBlank]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $vatRate = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $addressStreet = null;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    private ?string $addressNumber = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $addressCity = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $addressCountry = null;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank]
    private ?string $addressPostalCode = null;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    private ?string $iban = null;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    private ?string $phone = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $representative = null;

    /**
     * Days from invoice issue date until due date. Null = due on issue date.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $paymentDueDays = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $issuesInvoices = false;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getRegistrationNumber(): ?string
    {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(string $registrationNumber): static
    {
        $this->registrationNumber = $registrationNumber;

        return $this;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): static
    {
        $this->vatNumber = $vatNumber;

        return $this;
    }

    public function getVatRate(): ?string
    {
        return $this->vatRate;
    }

    public function setVatRate(string $vatRate): static
    {
        $this->vatRate = $vatRate;

        return $this;
    }

    public function getAddressStreet(): ?string
    {
        return $this->addressStreet;
    }

    public function setAddressStreet(string $addressStreet): static
    {
        $this->addressStreet = $addressStreet;

        return $this;
    }

    public function getAddressNumber(): ?string
    {
        return $this->addressNumber;
    }

    public function setAddressNumber(string $addressNumber): static
    {
        $this->addressNumber = $addressNumber;

        return $this;
    }

    public function getAddressCity(): ?string
    {
        return $this->addressCity;
    }

    public function setAddressCity(string $addressCity): static
    {
        $this->addressCity = $addressCity;

        return $this;
    }

    public function getAddressCountry(): ?string
    {
        return $this->addressCountry;
    }

    public function setAddressCountry(string $addressCountry): static
    {
        $this->addressCountry = $addressCountry;

        return $this;
    }

    public function getAddressPostalCode(): ?string
    {
        return $this->addressPostalCode;
    }

    public function setAddressPostalCode(string $addressPostalCode): static
    {
        $this->addressPostalCode = $addressPostalCode;

        return $this;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function setIban(string $iban): static
    {
        $this->iban = $iban;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getRepresentative(): ?string
    {
        return $this->representative;
    }

    public function setRepresentative(?string $representative): static
    {
        $this->representative = $representative;

        return $this;
    }

    public function getPaymentDueDays(): ?int
    {
        return $this->paymentDueDays;
    }

    public function setPaymentDueDays(?int $paymentDueDays): static
    {
        $this->paymentDueDays = $paymentDueDays;

        return $this;
    }

    public function isIssuesInvoices(): bool
    {
        return $this->issuesInvoices;
    }

    public function setIssuesInvoices(bool $issuesInvoices): static
    {
        $this->issuesInvoices = $issuesInvoices;

        return $this;
    }

    public function __toString(): string
    {
        return (string) ($this->name ?? '');
    }
}

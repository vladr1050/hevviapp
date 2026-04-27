<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TermsAudience;
use App\Repository\PortalLoginConsentLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Audit trail: portal JWT login with acceptance of published Terms for the selected audience.
 */
#[ORM\Entity(repositoryClass: PortalLoginConsentLogRepository::class)]
#[ORM\Table(name: 'portal_login_consent_log')]
#[ORM\Index(name: 'idx_portal_login_consent_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_portal_login_consent_email', columns: ['email'])]
class PortalLoginConsentLog extends BaseUUID
{
    #[ORM\Column(length: 255)]
    private string $email = '';

    #[ORM\Column(length: 20)]
    private string $accountType = 'user';

    #[ORM\Column(length: 20, enumType: TermsAudience::class)]
    private TermsAudience $portalAudience = TermsAudience::Sender;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $subjectId = null;

    #[ORM\ManyToOne(targetEntity: TermsOfUseRevision::class)]
    #[ORM\JoinColumn(name: 'terms_of_use_revision_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?TermsOfUseRevision $termsRevision = null;

    #[ORM\Column(nullable: true)]
    private ?int $termsVersion = null;

    #[ORM\Column(length: 45)]
    private string $ipAddress = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAccountType(): string
    {
        return $this->accountType;
    }

    public function setAccountType(string $accountType): static
    {
        $this->accountType = $accountType;

        return $this;
    }

    public function getPortalAudience(): TermsAudience
    {
        return $this->portalAudience;
    }

    public function setPortalAudience(TermsAudience $portalAudience): static
    {
        $this->portalAudience = $portalAudience;

        return $this;
    }

    public function getSubjectId(): ?Uuid
    {
        return $this->subjectId;
    }

    public function setSubjectId(?Uuid $subjectId): static
    {
        $this->subjectId = $subjectId;

        return $this;
    }

    public function getTermsRevision(): ?TermsOfUseRevision
    {
        return $this->termsRevision;
    }

    public function setTermsRevision(?TermsOfUseRevision $termsRevision): static
    {
        $this->termsRevision = $termsRevision;

        return $this;
    }

    public function getTermsVersion(): ?int
    {
        return $this->termsVersion;
    }

    public function setTermsVersion(?int $termsVersion): static
    {
        $this->termsVersion = $termsVersion;

        return $this;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s @ %s', $this->email, $this->getCreatedAt()?->format('c') ?? '');
    }
}

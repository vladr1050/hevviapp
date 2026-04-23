<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TermsAudience;
use App\Enum\TermsRevisionStatus;
use App\Repository\TermsOfUseRevisionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Versioned legal HTML for portal Terms and Conditions (carrier vs sender).
 */
#[ORM\Entity(repositoryClass: TermsOfUseRevisionRepository::class)]
#[ORM\Table(name: 'terms_of_use_revision')]
#[ORM\UniqueConstraint(name: 'terms_audience_version_uniq', columns: ['audience', 'version'])]
class TermsOfUseRevision extends BaseUUID
{
    #[ORM\Column(length: 20, enumType: TermsAudience::class)]
    private TermsAudience $audience = TermsAudience::Sender;

    #[ORM\Column]
    private int $version = 1;

    #[ORM\Column(length: 512)]
    private string $title = '';

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $subtitle = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $bodyHtml = '';

    #[ORM\Column(length: 20, enumType: TermsRevisionStatus::class)]
    private TermsRevisionStatus $status = TermsRevisionStatus::Draft;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    public function getAudience(): TermsAudience
    {
        return $this->audience;
    }

    public function setAudience(TermsAudience $audience): static
    {
        $this->audience = $audience;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): static
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function getBodyHtml(): string
    {
        return $this->bodyHtml;
    }

    public function setBodyHtml(string $bodyHtml): static
    {
        $this->bodyHtml = $bodyHtml;

        return $this;
    }

    public function getStatus(): TermsRevisionStatus
    {
        return $this->status;
    }

    public function setStatus(TermsRevisionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf(
            'v%d %s (%s)',
            $this->version,
            $this->audience->value,
            $this->status->value,
        );
    }
}

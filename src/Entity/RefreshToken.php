<?php

namespace App\Entity;

use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_token')]
#[ORM\Index(name: 'idx_refresh_token_token', columns: ['token'])]
#[ORM\Index(name: 'idx_refresh_token_expires_at', columns: ['expires_at'])]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 128, unique: true)]
    private string $token;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Carrier::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Carrier $carrier = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(UserInterface $subject, string $token, \DateTimeImmutable $expiresAt)
    {
        if ($subject instanceof User) {
            $this->user = $subject;
        } elseif ($subject instanceof Carrier) {
            $this->carrier = $subject;
        } else {
            throw new \InvalidArgumentException('Subject must be User or Carrier');
        }

        $this->token = $token;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getSubject(): UserInterface
    {
        return $this->user ?? $this->carrier ?? throw new \LogicException('RefreshToken has no subject');
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getCarrier(): ?Carrier
    {
        return $this->carrier;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }
}

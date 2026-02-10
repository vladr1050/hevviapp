<?php

namespace App\Entity;

use App\Repository\OrderHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderHistoryRepository::class)]
class OrderHistory extends BaseUUID
{
    public const array CHANGED_BY = [
        'USER' => 1,
        'CARRIER' => 2,
        'SYSTEM' => 3,
        'MANUAL' => 4,
    ];

    #[ORM\ManyToOne(inversedBy: 'histories')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Order $relatedOrder = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $status = null;

    #[ORM\Column]
    private ?int $changedBy = null;

    #[ORM\Column(nullable: true)]
    private ?array $meta = null;

    public function getRelatedOrder(): ?Order
    {
        return $this->relatedOrder;
    }

    public function setRelatedOrder(?Order $relatedOrder): static
    {
        $this->relatedOrder = $relatedOrder;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getChangedBy(): ?int
    {
        return $this->changedBy;
    }

    public function setChangedBy(int $changedBy): static
    {
        $this->changedBy = $changedBy;

        return $this;
    }

    public function getMeta(): ?array
    {
        return $this->meta;
    }

    public function setMeta(?array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    public function __toString(): string
    {
        $statusKey = array_flip(\App\Entity\Order::STATUS)[$this->status] ?? 'UNKNOWN';
        $changedByKey = array_flip(self::CHANGED_BY)[$this->changedBy] ?? 'UNKNOWN';
        $date = $this->getCreatedAt() ? $this->getCreatedAt()->format('Y-m-d H:i') : 'N/A';

        return sprintf(
            'Status: %s | Changed by: %s | At: %s',
            ucfirst(strtolower($statusKey)),
            ucfirst(strtolower($changedByKey)),
            $date
        );
    }
}

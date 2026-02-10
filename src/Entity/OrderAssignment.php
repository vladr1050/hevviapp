<?php

namespace App\Entity;

use App\Repository\OrderAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderAssignmentRepository::class)]
class OrderAssignment extends BaseUUID
{
    public const array STATUS = [
        'ASSIGNED' => 1,
        'ACCEPTED' => 2,
        'REJECTED' => -1
    ];
    #[ORM\ManyToOne(inversedBy: 'orderAssignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Carrier $carrier = null;

    #[ORM\ManyToOne(inversedBy: 'orderAssignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Order $relatedOrder = null;

    #[ORM\ManyToOne(inversedBy: 'orderAssignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Manager $assignedBy = null;

    #[ORM\Column]
    private ?int $status = self::STATUS['ASSIGNED'];

    public function getCarrier(): ?Carrier
    {
        return $this->carrier;
    }

    public function setCarrier(?Carrier $carrier): static
    {
        $this->carrier = $carrier;

        return $this;
    }

    public function getRelatedOrder(): ?Order
    {
        return $this->relatedOrder;
    }

    public function setRelatedOrder(?Order $relatedOrder): static
    {
        $this->relatedOrder = $relatedOrder;

        return $this;
    }

    public function getAssignedBy(): ?Manager
    {
        return $this->assignedBy;
    }

    public function setAssignedBy(?Manager $assignedBy): static
    {
        $this->assignedBy = $assignedBy;

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

    public function __toString(): string
    {
        $carrierName = $this->carrier ? $this->carrier->getLegalName() : 'N/A';
        $statusName = array_flip(self::STATUS)[$this->status] ?? 'UNKNOWN';

        return sprintf('Assignment: %s - %s', $carrierName, ucfirst(strtolower($statusName)));
    }
}

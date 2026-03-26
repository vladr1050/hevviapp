<?php

namespace App\Entity;

use App\Repository\OrderAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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

    /**
     * Запрещает создание нового OrderAssignment, пока у заказа есть
     * хотя бы одно активное (не REJECTED) назначение.
     * Чтобы создать новое — необходимо сначала перевести предыдущее в REJECTED.
     */
    #[Assert\Callback]
    public function validateNoActiveAssignment(ExecutionContextInterface $context): void
    {
        if ($this->relatedOrder === null) {
            return;
        }

        foreach ($this->relatedOrder->getOrderAssignments() as $existing) {
            if ($existing === $this) {
                continue;
            }

            if ($existing->getStatus() !== self::STATUS['REJECTED']) {
                $context->buildViolation(
                    'order_assignment.error.active_assignment_exists'
                )
                    ->atPath('carrier')
                    ->addViolation();

                return;
            }
        }
    }

    public function __toString(): string
    {
        $carrierName = $this->carrier ? $this->carrier->getLegalName() : 'N/A';
        $statusName = array_flip(self::STATUS)[$this->status] ?? 'UNKNOWN';

        return sprintf('Assignment: %s - %s', $carrierName, ucfirst(strtolower($statusName)));
    }
}

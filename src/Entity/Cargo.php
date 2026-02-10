<?php

namespace App\Entity;

use App\Repository\CargoRepository;
use App\Validator\DimensionsFormat;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CargoRepository::class)]
#[ORM\Index(columns: ['type'])]
#[ORM\Index(columns: ['weight_kg'])]
#[ORM\Index(columns: ['stackable'])]
#[ORM\Index(columns: ['manipulator_needed'])]
class Cargo extends BaseUUID
{
    public const array TYPE = [
        'PALLET' => 1,
        'OVERSIZED' => 2,
    ];

    #[ORM\Column]
    private ?int $type = self::TYPE['PALLET'];

    #[ORM\Column]
    private ?int $quantity = null;

    #[ORM\Column]
    private ?int $weightKg = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[DimensionsFormat]
    private ?string $dimensionsCm = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column]
    private ?bool $stackable = null;

    #[ORM\Column]
    private ?bool $manipulatorNeeded = null;

    #[ORM\ManyToOne(inversedBy: 'cargo')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Order $relatedOrder = null;

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getWeightKg(): ?int
    {
        return $this->weightKg;
    }

    public function setWeightKg(int $weightKg): static
    {
        $this->weightKg = $weightKg;

        return $this;
    }

    public function getDimensionsCm(): ?string
    {
        return $this->dimensionsCm;
    }

    public function setDimensionsCm(?string $dimensionsCm): static
    {
        $this->dimensionsCm = $dimensionsCm;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function isStackable(): ?bool
    {
        return $this->stackable;
    }

    public function setStackable(bool $stackable): static
    {
        $this->stackable = $stackable;

        return $this;
    }

    public function isManipulatorNeeded(): ?bool
    {
        return $this->manipulatorNeeded;
    }

    public function setManipulatorNeeded(bool $manipulatorNeeded): static
    {
        $this->manipulatorNeeded = $manipulatorNeeded;

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

    public function __toString(): string
    {
        return sprintf('%s (%d kg)', $this->getName() ?? 'Unnamed Cargo', $this->getWeightKg() ?? 0);
    }
}

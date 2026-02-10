<?php

namespace App\Entity;

use App\Repository\MatrixItemRepository;
use App\Twig\Extension\Filter\MoneyExtension;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MatrixItemRepository::class)]
class MatrixItem extends BaseUUID
{
    #[ORM\Column]
    private ?int $price = null;

    #[ORM\ManyToOne(inversedBy: 'matrixItems', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ServiceArea $serviceArea = null;

    #[ORM\Column]
    private ?int $weightFrom = null;

    #[ORM\Column]
    private ?int $weightTo = null;

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getServiceArea(): ?ServiceArea
    {
        return $this->serviceArea;
    }

    public function setServiceArea(?ServiceArea $serviceArea): static
    {
        $this->serviceArea = $serviceArea;

        return $this;
    }

    public function __toString(): string
    {
        $money = new MoneyExtension();
        $price = $money->currencyConvert($this->price ?? 0, $this->serviceArea?->getCurrency() ?? 'EUR');

        return sprintf(
            '%d-%d km: %s',
            $this->weightFrom ?? 0,
            $this->weightTo ?? 0,
            $price,
        );
    }

    public function getWeightFrom(): ?int
    {
        return $this->weightFrom;
    }

    public function setWeightFrom(int $weightFrom): static
    {
        $this->weightFrom = $weightFrom;

        return $this;
    }

    public function getWeightTo(): ?int
    {
        return $this->weightTo;
    }

    public function setWeightTo(int $weightTo): static
    {
        $this->weightTo = $weightTo;

        return $this;
    }
}

<?php

namespace App\Entity;

use App\Repository\ServiceAreaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints\Currency;

#[ORM\Entity(repositoryClass: ServiceAreaRepository::class)]
class ServiceArea extends BaseUUID
{
    public const array CURRENCY = [
        'USD' => 'USD',
        'EUR' => 'EUR',
        'GBP' => 'GBP',
    ];
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, MatrixItem>
     */
    #[ORM\OneToMany(targetEntity: MatrixItem::class, mappedBy: 'serviceArea', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $matrixItems;

    /**
     * @var Collection<int, GeoArea>
     */
    #[ORM\ManyToMany(targetEntity: GeoArea::class, inversedBy: 'serviceAreas')]
    private Collection $geoAreas;

    #[ORM\Column(length: 255)]
    private ?string $currency = self::CURRENCY['EUR'];

    public function __construct()
    {
        $this->matrixItems = new ArrayCollection();
        $this->geoAreas = new ArrayCollection();
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

    public function __toString(): string
    {
        return $this->name ?? parent::__toString();
    }

    /**
     * @return Collection<int, MatrixItem>
     */
    public function getMatrixItems(): Collection
    {
        return $this->matrixItems;
    }

    public function addMatrixItem(MatrixItem $matrixItem): static
    {
        if (!$this->matrixItems->contains($matrixItem)) {
            $this->matrixItems->add($matrixItem);
            $matrixItem->setServiceArea($this);
        }

        return $this;
    }

    public function removeMatrixItem(MatrixItem $matrixItem): static
    {
        if ($this->matrixItems->removeElement($matrixItem)) {
            // set the owning side to null (unless already changed)
            if ($matrixItem->getServiceArea() === $this) {
                $matrixItem->setServiceArea(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, GeoArea>
     */
    public function getGeoAreas(): Collection
    {
        return $this->geoAreas;
    }

    public function addGeoArea(GeoArea $geoArea): static
    {
        if (!$this->geoAreas->contains($geoArea)) {
            $this->geoAreas->add($geoArea);
        }

        return $this;
    }

    public function removeGeoArea(GeoArea $geoArea): static
    {
        $this->geoAreas->removeElement($geoArea);

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }
}

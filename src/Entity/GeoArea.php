<?php

namespace App\Entity;

use App\Repository\GeoAreaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Jsor\Doctrine\PostGIS\Types\PostGISType;

#[ORM\Entity(repositoryClass: GeoAreaRepository::class)]
#[ORM\Index(columns: ['scope'])]
#[ORM\Index(columns: ['scope', 'country_iso3'])]
#[ORM\Index(name: 'idx_geo_area_geometry', columns: ['geometry'], flags: ['spatial'])]
class GeoArea extends BaseUUID
{
    public const SCOPE = [
        'COUNTRY' => 1,
        'CITY' => 2,
        'CUSTOM_AREA' => 3,
    ];

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $scope = self::SCOPE['COUNTRY'];

    #[ORM\Column(
        type: PostGISType::GEOMETRY,
        options: [
            'geometry_type' => 'MULTIPOLYGON',
            'srid' => 4326,
        ],
    )]
    private ?string $geometry = null;

    /**
     * @var Collection<int, ServiceArea>
     */
    #[ORM\ManyToMany(targetEntity: ServiceArea::class, mappedBy: 'geoAreas')]
    private Collection $serviceAreas;

    #[ORM\Column(length: 255)]
    private ?string $countryISO3 = null;

    public function __construct()
    {
        $this->serviceAreas = new ArrayCollection();
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

    public function getScope(): ?int
    {
        return $this->scope;
    }

    public function setScope(int $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function getGeometry(): ?string
    {
        return $this->geometry;
    }

    public function setGeometry(string $geometry): static
    {
        $this->geometry = $geometry;

        return $this;
    }

    /**
     * @return Collection<int, ServiceArea>
     */
    public function getServiceAreas(): Collection
    {
        return $this->serviceAreas;
    }

    public function addServiceArea(ServiceArea $serviceArea): static
    {
        if (!$this->serviceAreas->contains($serviceArea)) {
            $this->serviceAreas->add($serviceArea);
            $serviceArea->addGeoArea($this);
        }

        return $this;
    }

    public function removeServiceArea(ServiceArea $serviceArea): static
    {
        if ($this->serviceAreas->removeElement($serviceArea)) {
            $serviceArea->removeGeoArea($this);
        }

        return $this;
    }

    public function getCountryISO3(): ?string
    {
        return $this->countryISO3;
    }

    public function setCountryISO3(string $countryISO3): static
    {
        $this->countryISO3 = $countryISO3;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? parent::__toString();
    }
}

<?php

/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Entity;

use App\Repository\AppSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Singleton-style site configuration for public map / address search (editable in Sonata).
 */
#[ORM\Entity(repositoryClass: AppSettingsRepository::class)]
#[ORM\Table(name: 'app_settings')]
class AppSettings extends BaseUUID
{
    #[ORM\Column(options: ['default' => false])]
    private bool $restrictGeographicSearch = false;

    /** Comma-separated ISO 3166-1 alpha-2 codes, e.g. "lv,lt,ee". */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nominatimCountryCodes = null;

    #[ORM\Column(nullable: true)]
    private ?float $bboxMinLatitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $bboxMaxLatitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $bboxMinLongitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $bboxMaxLongitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $defaultMapLatitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $defaultMapLongitude = null;

    #[ORM\Column(nullable: true)]
    private ?int $defaultMapZoom = null;

    public function isRestrictGeographicSearch(): bool
    {
        return $this->restrictGeographicSearch;
    }

    public function setRestrictGeographicSearch(bool $restrictGeographicSearch): static
    {
        $this->restrictGeographicSearch = $restrictGeographicSearch;

        return $this;
    }

    public function getNominatimCountryCodes(): ?string
    {
        return $this->nominatimCountryCodes;
    }

    public function setNominatimCountryCodes(?string $nominatimCountryCodes): static
    {
        $this->nominatimCountryCodes = $nominatimCountryCodes;

        return $this;
    }

    public function getBboxMinLatitude(): ?float
    {
        return $this->bboxMinLatitude;
    }

    public function setBboxMinLatitude(?float $bboxMinLatitude): static
    {
        $this->bboxMinLatitude = $bboxMinLatitude;

        return $this;
    }

    public function getBboxMaxLatitude(): ?float
    {
        return $this->bboxMaxLatitude;
    }

    public function setBboxMaxLatitude(?float $bboxMaxLatitude): static
    {
        $this->bboxMaxLatitude = $bboxMaxLatitude;

        return $this;
    }

    public function getBboxMinLongitude(): ?float
    {
        return $this->bboxMinLongitude;
    }

    public function setBboxMinLongitude(?float $bboxMinLongitude): static
    {
        $this->bboxMinLongitude = $bboxMinLongitude;

        return $this;
    }

    public function getBboxMaxLongitude(): ?float
    {
        return $this->bboxMaxLongitude;
    }

    public function setBboxMaxLongitude(?float $bboxMaxLongitude): static
    {
        $this->bboxMaxLongitude = $bboxMaxLongitude;

        return $this;
    }

    public function hasCompleteBoundingBox(): bool
    {
        return $this->bboxMinLatitude !== null
            && $this->bboxMaxLatitude !== null
            && $this->bboxMinLongitude !== null
            && $this->bboxMaxLongitude !== null;
    }

    public function getDefaultMapLatitude(): ?float
    {
        return $this->defaultMapLatitude;
    }

    public function setDefaultMapLatitude(?float $defaultMapLatitude): static
    {
        $this->defaultMapLatitude = $defaultMapLatitude;

        return $this;
    }

    public function getDefaultMapLongitude(): ?float
    {
        return $this->defaultMapLongitude;
    }

    public function setDefaultMapLongitude(?float $defaultMapLongitude): static
    {
        $this->defaultMapLongitude = $defaultMapLongitude;

        return $this;
    }

    public function getDefaultMapZoom(): ?int
    {
        return $this->defaultMapZoom;
    }

    public function setDefaultMapZoom(?int $defaultMapZoom): static
    {
        $this->defaultMapZoom = $defaultMapZoom;

        return $this;
    }

    public function __toString(): string
    {
        return 'Map & address settings';
    }
}

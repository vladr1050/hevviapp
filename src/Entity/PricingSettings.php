<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PricingSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Singleton-style global delivery pricing settings.
 */
#[ORM\Entity(repositoryClass: PricingSettingsRepository::class)]
#[ORM\Table(name: 'pricing_settings')]
class PricingSettings extends BaseUUID
{
    /** Global multiplier on base freight when carrier has no local coefficient. */
    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 4, options: ['default' => '1.0000'])]
    private string $defaultPriceCoefficient = '1.0000';

    public function getDefaultPriceCoefficient(): string
    {
        return $this->defaultPriceCoefficient;
    }

    public function setDefaultPriceCoefficient(string $defaultPriceCoefficient): static
    {
        $this->defaultPriceCoefficient = $defaultPriceCoefficient;

        return $this;
    }

    public function __toString(): string
    {
        return 'Delivery pricing settings';
    }
}

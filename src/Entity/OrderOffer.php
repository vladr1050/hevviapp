<?php

namespace App\Entity;

use App\Enum\OfferPricingSource;
use App\Repository\OrderOfferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderOfferRepository::class)]
class OrderOffer extends BaseUUID
{
    public const array STATUS = [
        'DRAFT' => 1,
        'ACCEPTED' => 2,
        'REJECTED' => -1
    ];
    #[ORM\Column]
    private ?int $brutto = null;

    #[ORM\Column]
    private ?int $netto = null;

    #[ORM\Column]
    private ?int $vat = null;

    #[ORM\ManyToOne(inversedBy: 'offers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Order $relatedOrder = null;

    #[ORM\Column]
    private ?int $status = null;

    #[ORM\Column(nullable: true)]
    private ?int $fee = null;

    #[ORM\Column(length: 32, options: ['default' => 'calculated'])]
    private string $pricingSource = 'calculated';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adjustmentReason = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Manager $adjustedByManager = null;

    #[ORM\OneToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'superseded_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?OrderOffer $supersededBy = null;

    public function getBrutto(): ?int
    {
        return $this->brutto;
    }

    public function setBrutto(int $brutto): static
    {
        $this->brutto = $brutto;

        return $this;
    }

    public function getNetto(): ?int
    {
        return $this->netto;
    }

    public function setNetto(int $netto): static
    {
        $this->netto = $netto;

        return $this;
    }

    public function getVat(): ?int
    {
        return $this->vat;
    }

    public function setVat(int $vat): static
    {
        $this->vat = $vat;

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
        $statusKey = array_search($this->status, self::STATUS, true);
        $statusLabel = is_string($statusKey) ? $statusKey : (string) ($this->status ?? '');

        return sprintf('Offer (%s)', $statusLabel);
    }

    public function getFee(): ?int
    {
        return $this->fee;
    }

    public function setFee(int $fee): static
    {
        $this->fee = $fee;

        return $this;
    }

    public function getPricingSource(): OfferPricingSource
    {
        return OfferPricingSource::from($this->pricingSource);
    }

    public function setPricingSource(OfferPricingSource $pricingSource): static
    {
        $this->pricingSource = $pricingSource->value;

        return $this;
    }

    public function getAdjustmentReason(): ?string
    {
        return $this->adjustmentReason;
    }

    public function setAdjustmentReason(?string $adjustmentReason): static
    {
        $this->adjustmentReason = $adjustmentReason;

        return $this;
    }

    public function getAdjustedByManager(): ?Manager
    {
        return $this->adjustedByManager;
    }

    public function setAdjustedByManager(?Manager $adjustedByManager): static
    {
        $this->adjustedByManager = $adjustedByManager;

        return $this;
    }

    public function getSupersededBy(): ?OrderOffer
    {
        return $this->supersededBy;
    }

    public function setSupersededBy(?OrderOffer $supersededBy): static
    {
        $this->supersededBy = $supersededBy;

        return $this;
    }

    public function isActiveDraftForOfferedOrder(): bool
    {
        return $this->status === self::STATUS['DRAFT']
            && $this->relatedOrder?->getStatus() === Order::STATUS['OFFERED'];
    }
}

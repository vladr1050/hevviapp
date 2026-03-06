<?php

namespace App\Entity;

use App\Repository\OrderOfferRepository;
use App\Twig\Extension\Filter\MoneyExtension;
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
        $money = new MoneyExtension();
        $gross = $money->currencyConvert($this->brutto ?? 0, $this->getRelatedOrder()?->getCurrency() ?? 'EUR');
        $net = $money->currencyConvert($this->netto ?? 0, $this->getRelatedOrder()?->getCurrency() ?? 'EUR');

        return sprintf(
            'Gross: %s | Net: %s | VAT: %d%%',
            $gross,
            $net,
            $this->vat ?? 0,
        );
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
}

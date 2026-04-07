<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'invoice')]
#[ORM\UniqueConstraint(name: 'UNIQ_INVOICE_NUMBER', columns: ['invoice_number'])]
#[ORM\UniqueConstraint(name: 'UNIQ_INVOICE_ORDER_OFFER', columns: ['order_offer_id'])]
class Invoice extends BaseUUID
{
    public const STATUS_PDF_FAILED = 1;

    /** PDF generated; buyer had no email — not sent. */
    public const STATUS_EMAIL_NOT_SENT = 2;

    public const STATUS_EMAIL_SENT = 3;

    public const STATUS_EMAIL_FAILED = 4;

    #[ORM\Column(length: 32)]
    private ?string $invoiceNumber = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $status = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $issueDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column]
    private ?int $amountFreight = null;

    #[ORM\Column]
    private ?int $amountCommission = null;

    #[ORM\Column]
    private ?int $amountSubtotal = null;

    #[ORM\Column]
    private ?int $amountVat = null;

    #[ORM\Column]
    private ?int $amountGross = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 4)]
    private ?string $vatRatePercent = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 4)]
    private ?string $feePercent = null;

    #[ORM\Column(length: 255)]
    private ?string $sellerName = null;

    #[ORM\Column(length: 255)]
    private ?string $sellerRegistrationNumber = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sellerVatNumber = null;

    #[ORM\Column(length: 512)]
    private ?string $sellerAddressLine1 = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $sellerAddressLine2 = null;

    #[ORM\Column(length: 255)]
    private ?string $sellerEmail = null;

    #[ORM\Column(length: 64)]
    private ?string $sellerPhone = null;

    #[ORM\Column(length: 255)]
    private ?string $buyerCompanyName = null;

    #[ORM\Column(length: 255)]
    private ?string $buyerRegistrationNumber = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $buyerVatNumber = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $buyerAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $buyerEmailSnapshot = null;

    #[ORM\Column(length: 64)]
    private ?string $orderReference = null;

    #[ORM\Column(length: 512)]
    private ?string $pickupAddress = null;

    #[ORM\Column(length: 512)]
    private ?string $deliveryAddress = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $pdfRelativePath = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pdfError = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $emailError = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'order_offer_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?OrderOffer $orderOffer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'related_order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Order $relatedOrder = null;

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(string $invoiceNumber): static
    {
        $this->invoiceNumber = $invoiceNumber;

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

    public function getIssueDate(): ?\DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function setIssueDate(\DateTimeImmutable $issueDate): static
    {
        $this->issueDate = $issueDate;

        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

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

    public function getAmountFreight(): ?int
    {
        return $this->amountFreight;
    }

    public function setAmountFreight(int $amountFreight): static
    {
        $this->amountFreight = $amountFreight;

        return $this;
    }

    public function getAmountCommission(): ?int
    {
        return $this->amountCommission;
    }

    public function setAmountCommission(int $amountCommission): static
    {
        $this->amountCommission = $amountCommission;

        return $this;
    }

    public function getAmountSubtotal(): ?int
    {
        return $this->amountSubtotal;
    }

    public function setAmountSubtotal(int $amountSubtotal): static
    {
        $this->amountSubtotal = $amountSubtotal;

        return $this;
    }

    public function getAmountVat(): ?int
    {
        return $this->amountVat;
    }

    public function setAmountVat(int $amountVat): static
    {
        $this->amountVat = $amountVat;

        return $this;
    }

    public function getAmountGross(): ?int
    {
        return $this->amountGross;
    }

    public function setAmountGross(int $amountGross): static
    {
        $this->amountGross = $amountGross;

        return $this;
    }

    public function getVatRatePercent(): ?string
    {
        return $this->vatRatePercent;
    }

    public function setVatRatePercent(string $vatRatePercent): static
    {
        $this->vatRatePercent = $vatRatePercent;

        return $this;
    }

    public function getFeePercent(): ?string
    {
        return $this->feePercent;
    }

    public function setFeePercent(string $feePercent): static
    {
        $this->feePercent = $feePercent;

        return $this;
    }

    public function getSellerName(): ?string
    {
        return $this->sellerName;
    }

    public function setSellerName(string $sellerName): static
    {
        $this->sellerName = $sellerName;

        return $this;
    }

    public function getSellerRegistrationNumber(): ?string
    {
        return $this->sellerRegistrationNumber;
    }

    public function setSellerRegistrationNumber(string $sellerRegistrationNumber): static
    {
        $this->sellerRegistrationNumber = $sellerRegistrationNumber;

        return $this;
    }

    public function getSellerVatNumber(): ?string
    {
        return $this->sellerVatNumber;
    }

    public function setSellerVatNumber(?string $sellerVatNumber): static
    {
        $this->sellerVatNumber = $sellerVatNumber;

        return $this;
    }

    public function getSellerAddressLine1(): ?string
    {
        return $this->sellerAddressLine1;
    }

    public function setSellerAddressLine1(string $sellerAddressLine1): static
    {
        $this->sellerAddressLine1 = $sellerAddressLine1;

        return $this;
    }

    public function getSellerAddressLine2(): ?string
    {
        return $this->sellerAddressLine2;
    }

    public function setSellerAddressLine2(?string $sellerAddressLine2): static
    {
        $this->sellerAddressLine2 = $sellerAddressLine2;

        return $this;
    }

    public function getSellerEmail(): ?string
    {
        return $this->sellerEmail;
    }

    public function setSellerEmail(string $sellerEmail): static
    {
        $this->sellerEmail = $sellerEmail;

        return $this;
    }

    public function getSellerPhone(): ?string
    {
        return $this->sellerPhone;
    }

    public function setSellerPhone(string $sellerPhone): static
    {
        $this->sellerPhone = $sellerPhone;

        return $this;
    }

    public function getBuyerCompanyName(): ?string
    {
        return $this->buyerCompanyName;
    }

    public function setBuyerCompanyName(string $buyerCompanyName): static
    {
        $this->buyerCompanyName = $buyerCompanyName;

        return $this;
    }

    public function getBuyerRegistrationNumber(): ?string
    {
        return $this->buyerRegistrationNumber;
    }

    public function setBuyerRegistrationNumber(string $buyerRegistrationNumber): static
    {
        $this->buyerRegistrationNumber = $buyerRegistrationNumber;

        return $this;
    }

    public function getBuyerVatNumber(): ?string
    {
        return $this->buyerVatNumber;
    }

    public function setBuyerVatNumber(?string $buyerVatNumber): static
    {
        $this->buyerVatNumber = $buyerVatNumber;

        return $this;
    }

    public function getBuyerAddress(): ?string
    {
        return $this->buyerAddress;
    }

    public function setBuyerAddress(string $buyerAddress): static
    {
        $this->buyerAddress = $buyerAddress;

        return $this;
    }

    public function getBuyerEmailSnapshot(): ?string
    {
        return $this->buyerEmailSnapshot;
    }

    public function setBuyerEmailSnapshot(?string $buyerEmailSnapshot): static
    {
        $this->buyerEmailSnapshot = $buyerEmailSnapshot;

        return $this;
    }

    public function getOrderReference(): ?string
    {
        return $this->orderReference;
    }

    public function setOrderReference(string $orderReference): static
    {
        $this->orderReference = $orderReference;

        return $this;
    }

    public function getPickupAddress(): ?string
    {
        return $this->pickupAddress;
    }

    public function setPickupAddress(string $pickupAddress): static
    {
        $this->pickupAddress = $pickupAddress;

        return $this;
    }

    public function getDeliveryAddress(): ?string
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(string $deliveryAddress): static
    {
        $this->deliveryAddress = $deliveryAddress;

        return $this;
    }

    public function getPdfRelativePath(): ?string
    {
        return $this->pdfRelativePath;
    }

    public function setPdfRelativePath(?string $pdfRelativePath): static
    {
        $this->pdfRelativePath = $pdfRelativePath;

        return $this;
    }

    public function getPdfError(): ?string
    {
        return $this->pdfError;
    }

    public function setPdfError(?string $pdfError): static
    {
        $this->pdfError = $pdfError;

        return $this;
    }

    public function getEmailError(): ?string
    {
        return $this->emailError;
    }

    public function setEmailError(?string $emailError): static
    {
        $this->emailError = $emailError;

        return $this;
    }

    public function getOrderOffer(): ?OrderOffer
    {
        return $this->orderOffer;
    }

    public function setOrderOffer(?OrderOffer $orderOffer): static
    {
        $this->orderOffer = $orderOffer;

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
        return (string) ($this->invoiceNumber ?? $this->id?->toRfc4122() ?? '');
    }
}

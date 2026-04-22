<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DocumentStatus;
use App\Enum\DocumentType;
use App\Repository\DocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Persisted order documents (PDF metadata). Generation and mail wiring come in a later iteration.
 */
#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'documents')]
#[ORM\UniqueConstraint(name: 'uniq_documents_order_type', columns: ['order_id', 'document_type'])]
#[ORM\UniqueConstraint(name: 'uniq_documents_document_number', columns: ['document_number'])]
#[ORM\Index(columns: ['document_type'])]
#[ORM\Index(columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class Document extends BaseUUID
{
    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Order $relatedOrder = null;

    #[ORM\Column(length: 32, enumType: DocumentType::class)]
    private DocumentType $documentType;

    #[ORM\Column(length: 64)]
    private string $documentNumber;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'related_document_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Document $relatedDocument = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'sender_company_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $senderCompany = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'receiver_company_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?BillingCompany $receiverCompany = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'carrier_company_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Carrier $carrierCompany = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(nullable: true)]
    private ?int $amountNet = null;

    #[ORM\Column(nullable: true)]
    private ?int $amountVat = null;

    #[ORM\Column(nullable: true)]
    private ?int $amountTotal = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $issuedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(length: 16, enumType: DocumentStatus::class)]
    private DocumentStatus $status = DocumentStatus::GENERATED;

    public function getRelatedOrder(): ?Order
    {
        return $this->relatedOrder;
    }

    public function setRelatedOrder(?Order $relatedOrder): static
    {
        $this->relatedOrder = $relatedOrder;

        return $this;
    }

    public function getDocumentType(): DocumentType
    {
        return $this->documentType;
    }

    public function setDocumentType(DocumentType $documentType): static
    {
        $this->documentType = $documentType;

        return $this;
    }

    public function getDocumentNumber(): string
    {
        return $this->documentNumber;
    }

    public function setDocumentNumber(string $documentNumber): static
    {
        $this->documentNumber = $documentNumber;

        return $this;
    }

    public function getRelatedDocument(): ?Document
    {
        return $this->relatedDocument;
    }

    public function setRelatedDocument(?Document $relatedDocument): static
    {
        $this->relatedDocument = $relatedDocument;

        return $this;
    }

    public function getSenderCompany(): ?User
    {
        return $this->senderCompany;
    }

    public function setSenderCompany(?User $senderCompany): static
    {
        $this->senderCompany = $senderCompany;

        return $this;
    }

    public function getReceiverCompany(): ?BillingCompany
    {
        return $this->receiverCompany;
    }

    public function setReceiverCompany(?BillingCompany $receiverCompany): static
    {
        $this->receiverCompany = $receiverCompany;

        return $this;
    }

    public function getCarrierCompany(): ?Carrier
    {
        return $this->carrierCompany;
    }

    public function setCarrierCompany(?Carrier $carrierCompany): static
    {
        $this->carrierCompany = $carrierCompany;

        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getAmountNet(): ?int
    {
        return $this->amountNet;
    }

    public function setAmountNet(?int $amountNet): static
    {
        $this->amountNet = $amountNet;

        return $this;
    }

    public function getAmountVat(): ?int
    {
        return $this->amountVat;
    }

    public function setAmountVat(?int $amountVat): static
    {
        $this->amountVat = $amountVat;

        return $this;
    }

    public function getAmountTotal(): ?int
    {
        return $this->amountTotal;
    }

    public function setAmountTotal(?int $amountTotal): static
    {
        $this->amountTotal = $amountTotal;

        return $this;
    }

    public function getIssuedAt(): ?\DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(\DateTimeImmutable $issuedAt): static
    {
        $this->issuedAt = $issuedAt;

        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getStatus(): DocumentStatus
    {
        return $this->status;
    }

    public function setStatus(DocumentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    #[ORM\PrePersist]
    public function ensureIssuedAt(): void
    {
        if ($this->issuedAt === null) {
            $this->issuedAt = new \DateTimeImmutable();
        }
    }

    public function __toString(): string
    {
        $id = $this->getId();

        return $id !== null ? $id->toRfc4122() : 'Document';
    }
}

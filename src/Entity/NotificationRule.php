<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationRuleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRuleRepository::class)]
#[ORM\Table(name: 'notification_rule')]
#[ORM\Index(name: 'idx_notification_rule_event_key', columns: ['event_key'])]
class NotificationRule extends BaseUUID
{
    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'event_key', length: 64)]
    private string $eventKey = '';

    #[ORM\Column(name: 'recipient_type', length: 32)]
    private string $recipientType = '';

    #[ORM\Column(name: 'subject_template', type: Types::TEXT)]
    private string $subjectTemplate = '';

    #[ORM\Column(name: 'body_template', type: Types::TEXT)]
    private string $bodyTemplate = '';

    #[ORM\Column(name: 'attach_invoice_pdf', options: ['default' => false])]
    private bool $attachInvoicePdf = false;

    /**
     * When non-empty, PDFs are loaded from {@see \App\Entity\Document} rows on the order (e.g. customer + carrier invoices after delivery).
     * Takes precedence over the legacy single attachment (invoice / passed document) for that rule.
     *
     * @var list<string>|null
     */
    #[ORM\Column(name: 'attach_document_types', type: Types::JSON, nullable: true)]
    private ?array $attachDocumentTypes = null;

    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'send_once_per_order', options: ['default' => false])]
    private bool $sendOncePerOrder = false;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getEventKey(): string
    {
        return $this->eventKey;
    }

    public function setEventKey(string $eventKey): static
    {
        $this->eventKey = $eventKey;

        return $this;
    }

    public function getRecipientType(): string
    {
        return $this->recipientType;
    }

    public function setRecipientType(string $recipientType): static
    {
        $this->recipientType = $recipientType;

        return $this;
    }

    public function getSubjectTemplate(): string
    {
        return $this->subjectTemplate;
    }

    public function setSubjectTemplate(string $subjectTemplate): static
    {
        $this->subjectTemplate = $subjectTemplate;

        return $this;
    }

    public function getBodyTemplate(): string
    {
        return $this->bodyTemplate;
    }

    public function setBodyTemplate(string $bodyTemplate): static
    {
        $this->bodyTemplate = $bodyTemplate;

        return $this;
    }

    public function isAttachInvoicePdf(): bool
    {
        return $this->attachInvoicePdf;
    }

    public function setAttachInvoicePdf(bool $attachInvoicePdf): static
    {
        $this->attachInvoicePdf = $attachInvoicePdf;

        return $this;
    }

    /**
     * @return list<string>|null
     */
    public function getAttachDocumentTypes(): ?array
    {
        return $this->attachDocumentTypes;
    }

    /**
     * @param list<string>|null $attachDocumentTypes
     */
    public function setAttachDocumentTypes(?array $attachDocumentTypes): static
    {
        if ($attachDocumentTypes === null || $attachDocumentTypes === []) {
            $this->attachDocumentTypes = null;

            return $this;
        }

        $this->attachDocumentTypes = array_values(array_unique(array_map(static fn ($v) => (string) $v, $attachDocumentTypes)));

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isSendOncePerOrder(): bool
    {
        return $this->sendOncePerOrder;
    }

    public function setSendOncePerOrder(bool $sendOncePerOrder): static
    {
        $this->sendOncePerOrder = $sendOncePerOrder;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : 'Notification rule';
    }
}

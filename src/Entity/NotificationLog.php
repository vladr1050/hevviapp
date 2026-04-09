<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationLogRepository::class)]
#[ORM\Table(name: 'notification_log')]
#[ORM\Index(name: 'idx_notification_log_order', columns: ['related_order_id'])]
#[ORM\Index(name: 'idx_notification_log_rule_order_event', columns: ['notification_rule_id', 'related_order_id', 'event_key', 'status'])]
class NotificationLog extends BaseUUID
{
    #[ORM\ManyToOne(inversedBy: 'notificationLogs')]
    #[ORM\JoinColumn(name: 'related_order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Order $relatedOrder = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'notification_rule_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?NotificationRule $notificationRule = null;

    #[ORM\Column(name: 'event_key', length: 64)]
    private string $eventKey = '';

    #[ORM\Column(name: 'recipient_type', length: 32)]
    private string $recipientType = '';

    #[ORM\Column(name: 'recipient_email', length: 255)]
    private string $recipientEmail = '';

    #[ORM\Column(name: 'subject_rendered', type: Types::TEXT)]
    private string $subjectRendered = '';

    #[ORM\Column(name: 'body_rendered', type: Types::TEXT)]
    private string $bodyRendered = '';

    #[ORM\Column(name: 'attachment_type', length: 32, nullable: true)]
    private ?string $attachmentType = null;

    #[ORM\Column(length: 16)]
    private string $status = '';

    #[ORM\Column(name: 'error_message', type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'provider_message_id', length: 255, nullable: true)]
    private ?string $providerMessageId = null;

    #[ORM\Column(name: 'sent_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    public function getRelatedOrder(): ?Order
    {
        return $this->relatedOrder;
    }

    public function setRelatedOrder(?Order $relatedOrder): static
    {
        $this->relatedOrder = $relatedOrder;

        return $this;
    }

    public function getNotificationRule(): ?NotificationRule
    {
        return $this->notificationRule;
    }

    public function setNotificationRule(?NotificationRule $notificationRule): static
    {
        $this->notificationRule = $notificationRule;

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

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }

    public function setRecipientEmail(string $recipientEmail): static
    {
        $this->recipientEmail = $recipientEmail;

        return $this;
    }

    public function getSubjectRendered(): string
    {
        return $this->subjectRendered;
    }

    public function setSubjectRendered(string $subjectRendered): static
    {
        $this->subjectRendered = $subjectRendered;

        return $this;
    }

    public function getBodyRendered(): string
    {
        return $this->bodyRendered;
    }

    public function setBodyRendered(string $bodyRendered): static
    {
        $this->bodyRendered = $bodyRendered;

        return $this;
    }

    public function getAttachmentType(): ?string
    {
        return $this->attachmentType;
    }

    public function setAttachmentType(?string $attachmentType): static
    {
        $this->attachmentType = $attachmentType;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getProviderMessageId(): ?string
    {
        return $this->providerMessageId;
    }

    public function setProviderMessageId(?string $providerMessageId): static
    {
        $this->providerMessageId = $providerMessageId;

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
}

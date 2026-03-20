<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains
 * the property of SIA SLYFOX, its suppliers and Customers,
 * if any.  The intellectual and technical concepts contained
 * herein are proprietary to SIA SLYFOX
 * its Suppliers and Customers are protected by trade secret or copyright law.
 *
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained.
 */

namespace App\Entity;

use App\Repository\OrderAttachmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderAttachmentRepository::class)]
#[ORM\Table(name: 'order_attachment')]
#[ORM\Index(name: 'idx_order_attachment_salt', columns: ['salt'])]
class OrderAttachment extends BaseUUID
{
    /**
     * Уникальный токен — единственный способ получить файл снаружи.
     * Путь на диске никогда не передаётся клиенту.
     * Генерируется как bin2hex(random_bytes(32)) = 64 hex-символа.
     */
    #[ORM\Column(length: 128, unique: true)]
    private string $salt = '';

    /**
     * Путь относительно public-директории.
     * Хранится в виде: uploads/orders/{salt}.pdf.gz
     */
    #[ORM\Column(length: 512)]
    private string $filePath = '';

    #[ORM\Column(length: 255)]
    private string $originalName = '';

    #[ORM\Column(type: Types::BIGINT)]
    private int $fileSize = 0;

    #[ORM\ManyToOne(inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Order $relatedOrder = null;

    public function getSalt(): string
    {
        return $this->salt;
    }

    public function setSalt(string $salt): static
    {
        $this->salt = $salt;

        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): static
    {
        $this->fileSize = $fileSize;

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
        return $this->originalName ?: 'attachment';
    }
}

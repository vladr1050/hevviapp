<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderAttachment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Отвечает за:
 * - валидацию PDF-файлов по MIME-типу
 * - генерацию криптографически-стойкого salt (64 hex-символа)
 * - gzip-сжатие (уровень 6) для экономии места на диске
 * - сохранение файла в public/uploads/orders/
 * - создание и persist сущности OrderAttachment
 * - удаление файла с диска при remove
 */
class OrderAttachmentUploader
{
    private const UPLOAD_SUBDIR   = 'uploads/orders';
    private const COMPRESS_LEVEL  = 6;
    private const ALLOWED_MIMES   = ['application/pdf', 'application/x-pdf'];

    public function __construct(
        private readonly string $publicDir,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Загружает, сжимает и сохраняет файл к заказу.
     * persist вызывается внутри; flush — на стороне вызывающего кода.
     *
     * @throws \InvalidArgumentException если файл не PDF
     * @throws \RuntimeException         если не удалось записать файл
     */
    public function upload(UploadedFile $file, Order $order): OrderAttachment
    {
        $this->assertPdf($file);

        $salt      = bin2hex(random_bytes(32));
        $targetDir = $this->publicDir . '/' . self::UPLOAD_SUBDIR;
        $this->ensureDirectoryExists($targetDir);

        $relPath     = self::UPLOAD_SUBDIR . '/' . $salt . '.pdf.gz';
        $absolutePath = $this->publicDir . '/' . $relPath;

        $this->compressToGzip($file->getPathname(), $absolutePath);

        $attachment = new OrderAttachment();
        $attachment->setSalt($salt);
        $attachment->setFilePath($relPath);
        $attachment->setOriginalName($file->getClientOriginalName() ?: 'document.pdf');
        $attachment->setFileSize((int) filesize($absolutePath));
        $attachment->setRelatedOrder($order);

        $this->em->persist($attachment);

        return $attachment;
    }

    /**
     * Удаляет файл с диска и помечает сущность для удаления из БД.
     * flush — на стороне вызывающего кода.
     */
    public function delete(OrderAttachment $attachment): void
    {
        $absolutePath = $this->publicDir . '/' . $attachment->getFilePath();

        if (is_file($absolutePath)) {
            unlink($absolutePath);
        }

        $this->em->remove($attachment);
    }

    // ------------------------------------------------------------------ private

    private function assertPdf(UploadedFile $file): void
    {
        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Only PDF files are allowed. Got: "%s".',
                    $file->getMimeType()
                )
            );
        }
    }

    private function ensureDirectoryExists(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Cannot create upload directory "%s".', $dir));
        }
    }

    private function compressToGzip(string $sourcePath, string $targetPath): void
    {
        $content = file_get_contents($sourcePath);

        if ($content === false) {
            throw new \RuntimeException(sprintf('Cannot read source file "%s".', $sourcePath));
        }

        $compressed = gzencode($content, self::COMPRESS_LEVEL);

        if ($compressed === false) {
            throw new \RuntimeException('gzip compression failed.');
        }

        if (file_put_contents($targetPath, $compressed) === false) {
            throw new \RuntimeException(sprintf('Cannot write compressed file to "%s".', $targetPath));
        }
    }
}

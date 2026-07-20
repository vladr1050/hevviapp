<?php

declare(strict_types=1);

namespace App\Service;

/**
 * MIME type for order attachment downloads from the original filename.
 */
final class OrderAttachmentContentTypeResolver
{
    public function resolveFromOriginalName(string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}

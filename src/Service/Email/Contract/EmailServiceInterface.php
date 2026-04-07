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

namespace App\Service\Email\Contract;

/**
 * Интерфейс для сервисов отправки email
 * Следует принципу Dependency Inversion Principle (SOLID)
 */
interface EmailServiceInterface
{
    /**
     * Отправка email
     *
     * @param string $to Адрес получателя
     * @param string $subject Тема письма
     * @param string $htmlContent HTML содержимое письма
     * @param string|null $textContent Текстовое содержимое письма (опционально)
     * @return bool Успешность отправки
     */
    public function send(
        string $to,
        string $subject,
        string $htmlContent,
        ?string $textContent = null
    ): bool;

    /**
     * @param non-empty-string $pdfBinary Raw PDF bytes
     */
    public function sendWithPdfAttachment(
        string $to,
        string $subject,
        string $htmlContent,
        ?string $textContent,
        string $attachmentFilename,
        string $pdfBinary,
    ): bool;
}

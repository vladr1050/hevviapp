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

namespace App\Service\OrderOffer\DTO;

/**
 * Class OrderOfferCalculationResultDto
 *
 * DTO для результата расчета стоимости заказа.
 * Следует принципам SOLID:
 * - Single Responsibility: только хранение данных результата расчета
 * - Immutable: все свойства readonly для безопасности
 */
final readonly class OrderOfferCalculationResultDto
{
    public function __construct(
        public bool    $success,
        public ?string $currency = null,
        public ?int    $bruttoPrice = null,
        public ?int    $nettoPrice = null,
        public ?int    $vatPercent = null,
        public ?string $errorMessage = null,
        public ?string $errorCode = null,
    )
    {
    }

    /**
     * Создать успешный результат расчета.
     */
    public static function success(string $currency, int $bruttoPrice, int $nettoPrice, int $vatPercent): self
    {
        return new self(
            success: true,
            currency: $currency,
            bruttoPrice: $bruttoPrice,
            nettoPrice: $nettoPrice,
            vatPercent: $vatPercent,
        );
    }

    /**
     * Создать результат с ошибкой.
     */
    public static function error(string $errorMessage, string $errorCode): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
        );
    }
}

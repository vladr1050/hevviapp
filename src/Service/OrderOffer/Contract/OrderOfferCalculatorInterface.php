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

namespace App\Service\OrderOffer\Contract;

use App\Entity\Order;
use App\Service\OrderOffer\DTO\OrderOfferCalculationResultDto;

/**
 * Interface OrderOfferCalculatorInterface
 * 
 * Интерфейс для расчета стоимости доставки заказа (OrderOffer).
 * Следует принципам SOLID:
 * - Interface Segregation: специфичный интерфейс для одной задачи
 * - Dependency Inversion: зависимость от абстракции, а не от реализации
 */
interface OrderOfferCalculatorInterface
{
    /**
     * Рассчитать стоимость доставки для заказа.
     * 
     * Алгоритм:
     * 1. Получить координаты точки доставки из Order
     * 2. Найти ServiceArea, которая содержит эту точку (через PostGIS)
     * 3. Вычислить общий вес всех Cargo в заказе
     * 4. Найти подходящий MatrixItem по весу в найденной ServiceArea
     * 5. Получить базовую цену (price) из MatrixItem
     * 6. Рассчитать комиссию, нетто, НДС (ставка из компании «Issues invoices», иначе TAX_VAT) и брутто
     * 
     * @param Order $order Заказ для расчета стоимости
     * 
     * @return OrderOfferCalculationResultDto Результат расчета с информацией об успехе/ошибке
     */
    public function calculate(Order $order): OrderOfferCalculationResultDto;
}

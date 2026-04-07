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

namespace App\Service\OrderOffer;

use App\Entity\Cargo;
use App\Entity\MatrixItem;
use App\Entity\Order;
use App\Entity\ServiceArea;
use App\Repository\ServiceAreaRepository;
use App\Service\Billing\IssuingCompanyResolver;
use App\Service\OrderOffer\Contract\OrderOfferCalculatorInterface;
use App\Service\OrderOffer\DTO\OrderOfferCalculationResultDto;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Class OrderOfferCalculatorService
 *
 * Сервис для расчета стоимости доставки заказа.
 * Следует принципам SOLID:
 * - Single Responsibility: отвечает только за расчет стоимости
 * - Open/Closed: расширяем через наследование, закрыт для модификации
 * - Liskov Substitution: реализует интерфейс OrderOfferCalculatorInterface
 * - Interface Segregation: использует специфичные интерфейсы
 * - Dependency Inversion: зависит от абстракций (интерфейсов)
 */
final class OrderOfferCalculatorService implements OrderOfferCalculatorInterface
{
    public function __construct(
        private readonly ServiceAreaRepository $serviceAreaRepository,
        private readonly LoggerInterface       $logger,
        private readonly IssuingCompanyResolver $issuingCompanyResolver,
        #[Autowire('%env(int:TAX_VAT)%')]
        private readonly int                   $defaultVatPercent,
        #[Autowire('%env(int:PLATFORM_FEE_PERCENT)%')]
        private readonly int                   $defaultPlatformFeePercent,
    )
    {
    }

    /**
     * {@inheritDoc}
     */
    public function calculate(Order $order): OrderOfferCalculationResultDto
    {
        try {
            // Шаг 1: Проверить наличие координат точки доставки
            $latitude = $order->getDropoutLatitude();
            $longitude = $order->getDropoutLongitude();

            if (!$latitude || !$longitude) {
                $this->logger->warning('Order missing dropout coordinates', [
                    'order_id' => $order->getId()?->toRfc4122(),
                ]);

                return OrderOfferCalculationResultDto::error(
                    errorMessage: 'order_offer.error.missing_coordinates',
                    errorCode: 'MISSING_COORDINATES',
                );
            }

            // Шаг 2: Найти ServiceArea по координатам
            $serviceArea = $this->serviceAreaRepository->findByCoordinates(
                (float)$latitude,
                (float)$longitude
            );

            if (!$serviceArea) {
                $this->logger->warning('ServiceArea not found for coordinates', [
                    'order_id' => $order->getId()?->toRfc4122(),
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);

                return OrderOfferCalculationResultDto::error(
                    errorMessage: 'order_offer.error.service_area_not_found',
                    errorCode: 'SERVICE_AREA_NOT_FOUND',
                );
            }

            // Шаг 3: Вычислить общий вес всех грузов
            $totalWeight = $this->calculateTotalWeight($order);

            if ($totalWeight <= 0) {
                $this->logger->warning('Order has no cargo or zero weight', [
                    'order_id' => $order->getId()?->toRfc4122(),
                ]);

                return OrderOfferCalculationResultDto::error(
                    errorMessage: 'order_offer.error.no_cargo_weight',
                    errorCode: 'NO_CARGO_WEIGHT',
                );
            }

            // Шаг 4: Найти подходящий MatrixItem по весу
            $matrixItem = $this->findMatrixItemByWeight($serviceArea, $totalWeight);

            if (!$matrixItem) {
                $this->logger->warning('MatrixItem not found for weight', [
                    'order_id' => $order->getId()?->toRfc4122(),
                    'service_area_id' => $serviceArea->getId()?->toRfc4122(),
                    'total_weight' => $totalWeight,
                ]);

                return OrderOfferCalculationResultDto::error(
                    errorMessage: 'order_offer.error.matrix_item_not_found',
                    errorCode: 'MATRIX_ITEM_NOT_FOUND',
                );
            }

            // Шаг 5: Получить базовый фрахт из матрицы (цена уже без НДС и без комиссии)
            $baseNetto = $matrixItem->getPrice();

            // Шаг 6: Процент комиссии — из компании «Issues invoices», иначе PLATFORM_FEE_PERCENT
            $feePercent = $this->resolvePlatformFeePercent();

            // fee = baseNetto × FEE%
            $feeAmount = $this->calculateFeeAmount($baseNetto, $feePercent);

            // Шаг 7: Нетто с комиссией (Subtotal no VAT)
            // netto = baseNetto + fee
            $nettoPrice = $baseNetto + $feeAmount;

            // Шаг 8: Ставка НДС — из компании, помеченной как выставляющая счета, иначе из TAX_VAT
            $vatRatePercent = $this->resolveVatRatePercent();

            // Шаг 9: Сумма НДС от нетто с комиссией
            $vatAmount = (int) round($nettoPrice * $vatRatePercent / 100);

            // Шаг 10: Брутто = нетто + НДС
            $bruttoPrice = $nettoPrice + $vatAmount;

            $this->logger->info('Successfully calculated order offer', [
                'order_id' => $order->getId()?->toRfc4122(),
                'service_area' => $serviceArea->getName(),
                'total_weight' => $totalWeight,
                'base_netto' => $baseNetto,
                'fee_percent' => $feePercent,
                'fee_amount' => $feeAmount,
                'netto_price' => $nettoPrice,
                'vat_percent' => $vatRatePercent,
                'vat_amount' => $vatAmount,
                'brutto_price' => $bruttoPrice,
            ]);

            return OrderOfferCalculationResultDto::success(
                currency: $serviceArea->getCurrency(),
                bruttoPrice: $bruttoPrice,
                nettoPrice: $nettoPrice,
                vatPercent: $vatRatePercent,
                vatAmount: $vatAmount,
                feePercent: $feePercent,
                feeAmount: $feeAmount,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Error calculating order offer', [
                'order_id' => $order->getId()?->toRfc4122(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return OrderOfferCalculationResultDto::error(
                errorMessage: 'order_offer.error.calculation_failed',
                errorCode: 'CALCULATION_FAILED',
            );
        }
    }

    /**
     * Вычислить общий вес всех грузов в заказе.
     *
     * @param Order $order Заказ
     *
     * @return int Общий вес в килограммах
     */
    private function calculateTotalWeight(Order $order): int
    {
        $totalWeight = 0;

        /** @var Cargo $cargo */
        foreach ($order->getCargo() as $cargo) {
            $quantity = $cargo->getQuantity() ?? 0;
            $weight = $cargo->getWeightKg() ?? 0;
            $totalWeight += $quantity * $weight;
        }

        return $totalWeight;
    }

    /**
     * Найти подходящий MatrixItem по весу груза.
     *
     * MatrixItem подходит, если:
     * - weightFrom <= totalWeight < weightTo
     *
     * @param ServiceArea $serviceArea Зона обслуживания
     * @param int $totalWeight Общий вес груза в кг
     *
     * @return MatrixItem|null Найденный MatrixItem или null
     */
    private function findMatrixItemByWeight(ServiceArea $serviceArea, int $totalWeight): ?MatrixItem
    {
        foreach ($serviceArea->getMatrixItems() as $matrixItem) {
            $weightFrom = $matrixItem->getWeightFrom() ?? 0;
            $weightTo = $matrixItem->getWeightTo() ?? PHP_INT_MAX;

            if ($totalWeight >= $weightFrom && $totalWeight <= $weightTo) {
                return $matrixItem;
            }
        }

        return null;
    }

    /**
     * Рассчитать сумму комиссии платформы от базового нетто.
     *
     * Формула: fee = round(baseNetto * feePercent / 100)
     *
     * @param int $baseNetto   Базовая нетто-цена (без VAT)
     * @param float $feePercent Процент комиссии платформы
     *
     * @return int Сумма комиссии, округлённая до целого
     */
    private function calculateFeeAmount(int $baseNetto, float $feePercent): int
    {
        return (int) round($baseNetto * $feePercent / 100);
    }

    /**
     * Процент комиссии из BillingCompany с «Issues invoices», иначе env PLATFORM_FEE_PERCENT.
     */
    private function resolvePlatformFeePercent(): float
    {
        $issuer = $this->issuingCompanyResolver->getIssuingCompany();
        if ($issuer !== null) {
            $p = $issuer->getPlatformFeePercent();
            if ($p !== null && $p !== '') {
                return (float) $p;
            }
        }

        return (float) $this->defaultPlatformFeePercent;
    }

    /**
     * Процент НДС из BillingCompany с «Issues invoices», иначе значение env TAX_VAT.
     */
    private function resolveVatRatePercent(): float
    {
        $issuer = $this->issuingCompanyResolver->getIssuingCompany();
        if ($issuer !== null) {
            $rate = $issuer->getVatRate();
            if ($rate !== null && $rate !== '') {
                return (float) $rate;
            }
        }

        return (float) $this->defaultVatPercent;
    }
}

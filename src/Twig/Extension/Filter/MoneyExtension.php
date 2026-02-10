<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2025 SIA SLYFOX.
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

namespace App\Twig\Extension\Filter;

use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MoneyExtension extends AbstractExtension
{
    private const DEFAULT_VALUE = 0;

    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'currency_convert',
                $this->currencyConvert(...)
            ),
        ];
    }

    public function currencyConvert(?int $amount, string $code = 'EUR'): string
    {
        return $this->format($amount, $code);
    }

    private function format(?int $amount, string $code = 'EUR'): string
    {
        if (null === $amount) {
            $amount = self::DEFAULT_VALUE;
        }
        $money = new Money($amount, new Currency($code));
        $currencies = new ISOCurrencies();

        $numberFormatter = new \NumberFormatter('de_DE', \NumberFormatter::CURRENCY);

        return (new IntlMoneyFormatter($numberFormatter, $currencies))->format($money);
    }
}

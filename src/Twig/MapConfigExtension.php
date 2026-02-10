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

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig Extension для предоставления конфигурации карты в шаблонах.
 * 
 * Следует принципам SOLID:
 * - Single Responsibility: предоставляет только конфигурацию карты
 * - Open/Closed: легко расширяется для добавления новых параметров
 * - Dependency Inversion: зависит от абстракции (параметры контейнера)
 */
final class MapConfigExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $nominatimUrl,
        private readonly float $defaultLat,
        private readonly float $defaultLng,
        private readonly int $defaultZoom,
        private readonly string $userAgent
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('map_config', [$this, 'getMapConfig']),
        ];
    }

    /**
     * Возвращает конфигурацию карты для использования в шаблонах.
     * 
     * @return array{
     *     nominatim_url: string,
     *     default_lat: float,
     *     default_lng: float,
     *     default_zoom: int,
     *     user_agent: string
     * }
     */
    public function getMapConfig(): array
    {
        return [
            'nominatim_url' => $this->nominatimUrl,
            'default_lat' => $this->defaultLat,
            'default_lng' => $this->defaultLng,
            'default_zoom' => $this->defaultZoom,
            'user_agent' => $this->userAgent,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'app.map_config';
    }
}

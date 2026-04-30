<?php

/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Exception\Map;

use RuntimeException;

final class GoogleGeocodeProxyException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

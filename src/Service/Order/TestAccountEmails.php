<?php

declare(strict_types=1);

namespace App\Service\Order;

/**
 * Canonical list of test sender / carrier emails (Stage 0 ops agreement).
 */
final class TestAccountEmails
{
    /** @var list<string> */
    public const SENDERS = [
        'mihailovs.sergejs@gmail.com',
        'v.reskajs@gmail.com',
        'sender@hevvi.app',
        'max@conceptica.design',
    ];

    /** @var list<string> */
    public const CARRIERS = [
        'support@hevvi.app',
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(array_unique([...self::SENDERS, ...self::CARRIERS]));
    }
}

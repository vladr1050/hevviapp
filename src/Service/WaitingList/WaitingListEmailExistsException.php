<?php

declare(strict_types=1);

namespace App\Service\WaitingList;

final class WaitingListEmailExistsException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('A user with this email already exists.');
    }
}

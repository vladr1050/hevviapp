<?php

/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Service\Security;

use App\Entity\BaseSecurityDBO;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Applies plainPassword to the persisted password hash (see PlainPasswordMutationHandler).
 */
final class PlainPasswordMutationHandler
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function apply(BaseSecurityDBO $entity): void
    {
        $plain = $entity->getPlainPassword();
        if ($plain === null) {
            return;
        }

        $trimmed = trim($plain);
        if ($trimmed === '') {
            $entity->setPlainPassword(null);

            return;
        }

        $entity->setPassword($this->hasher->hashPassword($entity, $trimmed));
        $entity->setPlainPassword(null);
    }
}

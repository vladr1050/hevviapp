<?php
/*
 * DOO TECHGURU Confidential
 * Copyright (C) 2022 DOO TECHGURU.
 * All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains
 * the property of DOO TECHGURU, its suppliers and Customers,
 * if any.  The intellectual and technical concepts contained
 * herein are proprietary to DOO TECHGURU
 * its Suppliers and Customers are protected by trade secret or copyright law.
 *
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained.
 */

namespace App\Listener;

use App\Entity\BaseSecurityDBO;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PlainPasswordListener
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function prePersist(BaseSecurityDBO $securityEntity): void
    {
        $this->password($securityEntity);
    }

    public function preUpdate(BaseSecurityDBO $securityEntity, PreUpdateEventArgs $event): void
    {
        $this->password($securityEntity);
    }

    private function password(BaseSecurityDBO $securityEntity): void
    {
        $plain = $securityEntity->getPlainPassword();
        if ($plain === null) {
            return;
        }
        // Sonata / HTML forms submit "" when the field is left blank; only null was skipped before,
        // which hashed an empty password and broke login (Sender/Carrier admin edit).
        $trimmed = trim($plain);
        if ($trimmed === '') {
            $securityEntity->setPlainPassword(null);

            return;
        }

        $securityEntity->setPassword($this->hasher->hashPassword($securityEntity, $trimmed));
        $securityEntity->setPlainPassword(null);
    }
}

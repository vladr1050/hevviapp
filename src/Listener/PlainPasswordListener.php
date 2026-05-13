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
use App\Service\Security\PlainPasswordMutationHandler;
use Doctrine\ORM\Event\PreUpdateEventArgs;

class PlainPasswordListener
{
    public function __construct(
        private readonly PlainPasswordMutationHandler $plainPasswordMutationHandler,
    ) {
    }

    public function prePersist(BaseSecurityDBO $securityEntity): void
    {
        $this->plainPasswordMutationHandler->apply($securityEntity);
    }

    public function preUpdate(BaseSecurityDBO $securityEntity, PreUpdateEventArgs $event): void
    {
        $this->plainPasswordMutationHandler->apply($securityEntity);
    }
}

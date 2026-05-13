<?php

/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Admin;

use App\Entity\BaseSecurityDBO;
use App\Service\Security\PlainPasswordMutationHandler;

/**
 * Sonata runs preUpdate before flush. When only the password changes, Doctrine sees no mapped-field
 * changes (plainPassword is not a column), so entity preUpdate listeners never run unless we hash here.
 */
trait SonataPlainPasswordAdminTrait
{
    private ?PlainPasswordMutationHandler $plainPasswordMutationHandler = null;

    public function setPlainPasswordMutationHandler(PlainPasswordMutationHandler $plainPasswordMutationHandler): void
    {
        $this->plainPasswordMutationHandler = $plainPasswordMutationHandler;
    }

    protected function applyPlainPasswordFromAdminForm(object $object): void
    {
        if ($this->plainPasswordMutationHandler === null || !$object instanceof BaseSecurityDBO) {
            return;
        }

        $this->plainPasswordMutationHandler->apply($object);
    }
}

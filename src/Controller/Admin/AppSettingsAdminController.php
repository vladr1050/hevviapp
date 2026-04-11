<?php

/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Controller\Admin;

use App\Entity\AppSettings;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Singleton UX: list opens the single edit screen (or create if none).
 */
class AppSettingsAdminController extends CRUDController
{
    public function listAction(Request $request): Response
    {
        $admin = $this->admin;
        $em = $admin->getModelManager()->getEntityManager(AppSettings::class);
        $existing = $em->getRepository(AppSettings::class)->findOneBy([]);

        if ($existing !== null) {
            return new RedirectResponse(
                $admin->generateObjectUrl('edit', $existing)
            );
        }

        return new RedirectResponse(
            $admin->generateUrl('create')
        );
    }
}

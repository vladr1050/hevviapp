<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\PricingSettings;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Singleton UX: list opens the single edit screen (or create if none).
 */
class PricingSettingsAdminController extends CRUDController
{
    public function listAction(Request $request): Response
    {
        $admin = $this->admin;
        $em = $admin->getModelManager()->getEntityManager(PricingSettings::class);
        $existing = $em->getRepository(PricingSettings::class)->findOneBy([]);

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

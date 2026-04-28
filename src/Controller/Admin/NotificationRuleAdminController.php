<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\NotificationRule;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class NotificationRuleAdminController extends CRUDController
{
    public function copyAction(Request $request, EntityManagerInterface $entityManager): Response
    {
        $object = $this->assertObjectExists($request, true);
        \assert($object instanceof NotificationRule);

        $this->admin->checkAccess('show', $object);
        $this->admin->checkAccess('create');

        $copy = (new NotificationRule())
            ->setName($this->buildCopyName($object->getName()))
            ->setDescription($object->getDescription())
            ->setEventKey($object->getEventKey())
            ->setRecipientType($object->getRecipientType())
            ->setSubjectTemplate($object->getSubjectTemplate())
            ->setBodyTemplate($object->getBodyTemplate())
            ->setAttachInvoicePdf($object->isAttachInvoicePdf())
            ->setAttachDocumentTypes($object->getAttachDocumentTypes())
            ->setSendOncePerOrder($object->isSendOncePerOrder())
            ->setIsActive(false);

        $entityManager->persist($copy);
        $entityManager->flush();

        $this->addFlash(
            'sonata_flash_success',
            $this->trans('flash.notification_rule_copied', [], 'AppBundle'),
        );

        return new RedirectResponse($this->admin->generateObjectUrl('edit', $copy));
    }

    private function buildCopyName(string $name): string
    {
        $suffix = $this->trans('notification_rule.copy_name_suffix', [], 'AppBundle');
        $base = preg_match('/\s*\([^)]*\)\s*$/', $name) === 1
            ? preg_replace('/\s*\([^)]*\)\s*$/', '', $name)
            : $name;

        return trim($base).' '.$suffix;
    }
}

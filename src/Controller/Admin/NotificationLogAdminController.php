<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\NotificationLog;
use App\Notification\NotificationAttachmentType;
use App\Notification\NotificationEventKey;
use App\Repository\InvoiceRepository;
use App\Service\Notification\NotificationRuleProcessor;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class NotificationLogAdminController extends CRUDController
{
    public function replayAction(
        Request $request,
        NotificationRuleProcessor $processor,
        InvoiceRepository $invoiceRepository,
    ): Response {
        $object = $this->assertObjectExists($request, true);
        \assert($object instanceof NotificationLog);

        $this->admin->checkAccess('show', $object);

        $order = $object->getRelatedOrder();
        if ($order === null) {
            $this->addFlash('sonata_flash_error', $this->trans('flash.notification_replay_no_order', [], 'AppBundle'));

            return new RedirectResponse($this->admin->generateObjectUrl('show', $object));
        }

        $eventKey = $object->getEventKey();
        $invoice = null;
        if ($eventKey === NotificationEventKey::ORDER_PRICE_CONFIRMED
            || $object->getAttachmentType() === NotificationAttachmentType::INVOICE_PDF) {
            $invoice = $invoiceRepository->findLatestWithPdfForOrder($order);
        }

        try {
            $result = $processor->process($order, $eventKey, $invoice, true);
            $this->addFlash(
                'sonata_flash_success',
                $this->trans('flash.notification_replay_result', [
                    '%sent%' => (string) $result->getSentCount(),
                    '%failed%' => (string) $result->getFailedCount(),
                    '%skipped%' => (string) $result->getSkippedCount(),
                ], 'AppBundle'),
            );
        } catch (\Throwable $e) {
            $this->addFlash(
                'sonata_flash_error',
                $this->trans('flash.notification_replay_error', ['%message%' => $e->getMessage()], 'AppBundle'),
            );
        }

        return new RedirectResponse($this->admin->generateObjectUrl('show', $object));
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Invoice;
use App\Entity\NotificationLog;
use App\Entity\NotificationRule;
use App\Entity\Order;
use App\Notification\NotificationAttachmentType;
use App\Notification\NotificationLogStatus;
use App\Repository\NotificationLogRepository;
use App\Repository\NotificationRuleRepository;
use App\Service\Email\Contract\EmailServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Executes notification rules for one order + event (used synchronously and from Messenger).
 */
final class NotificationRuleProcessor
{
    public function __construct(
        private readonly NotificationRuleRepository $ruleRepository,
        private readonly NotificationLogRepository $logRepository,
        private readonly NotificationContextFactory $contextFactory,
        private readonly NotificationTemplateRenderer $templateRenderer,
        private readonly NotificationRecipientResolver $recipientResolver,
        private readonly NotificationAttachmentResolver $attachmentResolver,
        private readonly EmailServiceInterface $emailService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(
        Order $order,
        string $eventKey,
        ?Invoice $invoice = null,
        bool $ignoreSendOnce = false,
    ): NotificationDispatchResult {
        $rules = $this->ruleRepository->findActiveByEventKey($eventKey);
        $result = new NotificationDispatchResult(count($rules));
        if ($rules === []) {
            return $result;
        }

        $variables = $this->contextFactory->build($order, $invoice);

        foreach ($rules as $rule) {
            try {
                $this->dispatchRule($rule, $order, $eventKey, $variables, $invoice, $result, $ignoreSendOnce);
            } catch (\Throwable $e) {
                $this->logger->error('Notification rule dispatch failed', [
                    'event_key' => $eventKey,
                    'rule_id' => $rule->getId()?->toRfc4122(),
                    'order_id' => $order->getId()?->toRfc4122(),
                    'exception' => $e->getMessage(),
                ]);
                $result->recordFailed($e->getMessage());
            }
        }

        return $result;
    }

    private function dispatchRule(
        NotificationRule $rule,
        Order $order,
        string $eventKey,
        array $variables,
        ?Invoice $invoice,
        NotificationDispatchResult $result,
        bool $ignoreSendOnce = false,
    ): void {
        if (!$ignoreSendOnce && $rule->isSendOncePerOrder()
            && $this->logRepository->hasSuccessfulSendForRuleAndOrder($rule, $order, $eventKey)) {
            $result->recordSkipped();

            return;
        }

        $to = $this->recipientResolver->resolveEmail($rule, $order);
        if ($to === null) {
            $this->persistLog(
                $rule,
                $order,
                $eventKey,
                $rule->getRecipientType(),
                '',
                '',
                '',
                null,
                NotificationLogStatus::FAILED,
                'No recipient email',
            );
            $this->em->flush();
            $result->recordFailed('No recipient email');

            return;
        }

        $subject = $this->templateRenderer->render($rule->getSubjectTemplate(), $variables);
        $bodyHtml = $this->templateRenderer->render($rule->getBodyTemplate(), $variables);
        $bodyText = $this->templateRenderer->toPlainText($bodyHtml);

        $attachment = null;
        $attachmentType = null;
        if ($rule->isAttachInvoicePdf()) {
            $attachment = $this->attachmentResolver->resolveInvoicePdf($invoice);
            if ($attachment === null) {
                $this->persistLog(
                    $rule,
                    $order,
                    $eventKey,
                    $rule->getRecipientType(),
                    $to,
                    $subject,
                    $bodyHtml,
                    null,
                    NotificationLogStatus::FAILED,
                    'Invoice PDF required by rule but file is missing',
                );
                $this->em->flush();
                $result->recordFailed('Invoice PDF missing');

                return;
            }
            $attachmentType = NotificationAttachmentType::INVOICE_PDF;
        }

        $log = $this->persistLog(
            $rule,
            $order,
            $eventKey,
            $rule->getRecipientType(),
            $to,
            $subject,
            $bodyHtml,
            $attachmentType,
            NotificationLogStatus::PENDING,
            null,
        );
        $this->em->flush();

        $ok = $attachment !== null
            ? $this->emailService->sendWithPdfAttachment(
                $to,
                $subject,
                $bodyHtml,
                $bodyText !== '' ? $bodyText : null,
                $attachment['filename'],
                $attachment['binary'],
            )
            : $this->emailService->send(
                $to,
                $subject,
                $bodyHtml,
                $bodyText !== '' ? $bodyText : null,
            );

        if ($ok) {
            $log->setStatus(NotificationLogStatus::SENT);
            $log->setSentAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $log->setErrorMessage(null);
            $result->recordSent();
        } else {
            $log->setStatus(NotificationLogStatus::FAILED);
            $log->setErrorMessage('Mail provider returned failure.');
            $result->recordFailed('Mail provider returned failure.');
        }

        $this->em->flush();
    }

    private function persistLog(
        NotificationRule $rule,
        Order $order,
        string $eventKey,
        string $recipientType,
        string $recipientEmail,
        string $subjectRendered,
        string $bodyRendered,
        ?string $attachmentType,
        string $status,
        ?string $errorMessage,
    ): NotificationLog {
        $log = new NotificationLog();
        $log->setRelatedOrder($order);
        $log->setNotificationRule($rule);
        $log->setEventKey($eventKey);
        $log->setRecipientType($recipientType);
        $log->setRecipientEmail($recipientEmail);
        $log->setSubjectRendered($subjectRendered);
        $log->setBodyRendered($bodyRendered);
        $log->setAttachmentType($attachmentType);
        $log->setStatus($status);
        $log->setErrorMessage($errorMessage);
        if ($status === NotificationLogStatus::SENT) {
            $log->setSentAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        }
        $this->em->persist($log);

        return $log;
    }
}

<?php

declare(strict_types=1);

namespace App\Service\WaitingList;

use App\Entity\NotificationLog;
use App\Entity\NotificationRule;
use App\Notification\NotificationLogStatus;
use App\Notification\NotificationRecipientType;
use App\Repository\BillingCompanyRepository;
use App\Repository\NotificationRuleRepository;
use App\Service\Email\Contract\EmailServiceInterface;
use App\Service\Notification\NotificationTemplateRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends waiting-list emails via Notification rules (no related Order).
 */
final class WaitingListNotificationDispatcher
{
    public function __construct(
        private readonly NotificationRuleRepository $ruleRepository,
        private readonly NotificationTemplateRenderer $templateRenderer,
        private readonly EmailServiceInterface $emailService,
        private readonly BillingCompanyRepository $billingCompanyRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, string> $variables
     */
    public function dispatch(string $eventKey, array $variables, string $applicantEmail): void
    {
        $rules = $this->ruleRepository->findActiveByEventKey($eventKey);
        if ($rules === []) {
            $this->logger->warning('No active notification rules for waiting-list event', [
                'event_key' => $eventKey,
            ]);

            return;
        }

        foreach ($rules as $rule) {
            try {
                $this->dispatchRule($rule, $eventKey, $variables, $applicantEmail);
            } catch (\Throwable $e) {
                $this->logger->error('Waiting-list notification rule failed', [
                    'event_key' => $eventKey,
                    'rule_id' => $rule->getId()?->toRfc4122(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param array<string, string> $variables
     */
    private function dispatchRule(
        NotificationRule $rule,
        string $eventKey,
        array $variables,
        string $applicantEmail,
    ): void {
        $to = $this->resolveRecipientEmail($rule, $variables, $applicantEmail);
        if ($to === null) {
            $this->persistLog(
                $rule,
                $eventKey,
                $rule->getRecipientType(),
                '',
                '',
                '',
                NotificationLogStatus::FAILED,
                'No recipient email',
            );
            $this->em->flush();

            return;
        }

        $subject = $this->templateRenderer->render($rule->getSubjectTemplate(), $variables);
        $bodyHtml = $this->templateRenderer->render($rule->getBodyTemplate(), $variables);
        $bodyText = $this->templateRenderer->toPlainText($bodyHtml);

        $log = $this->persistLog(
            $rule,
            $eventKey,
            $rule->getRecipientType(),
            $to,
            $subject,
            $bodyHtml,
            NotificationLogStatus::PENDING,
            null,
        );
        $this->em->flush();

        $ok = $this->emailService->send(
            $to,
            $subject,
            $bodyHtml,
            $bodyText !== '' ? $bodyText : null,
        );

        if ($ok) {
            $log->setStatus(NotificationLogStatus::SENT);
            $log->setSentAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $log->setErrorMessage(null);
        } else {
            $log->setStatus(NotificationLogStatus::FAILED);
            $log->setErrorMessage('Mail provider returned failure.');
        }

        $this->em->flush();
    }

    /**
     * @param array<string, string> $variables
     */
    private function resolveRecipientEmail(
        NotificationRule $rule,
        array $variables,
        string $applicantEmail,
    ): ?string {
        return match ($rule->getRecipientType()) {
            NotificationRecipientType::APPLICANT => $this->nonEmptyEmail($applicantEmail),
            NotificationRecipientType::OPERATOR => $this->resolveOperatorEmail(),
            default => null,
        };
    }

    private function resolveOperatorEmail(): ?string
    {
        $issuer = $this->billingCompanyRepository->findIssuingCompany();
        if ($issuer === null) {
            return null;
        }

        return $this->nonEmptyEmail((string) ($issuer->getEmail() ?? ''));
    }

    private function nonEmptyEmail(string $email): ?string
    {
        $trimmed = trim($email);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function persistLog(
        NotificationRule $rule,
        string $eventKey,
        string $recipientType,
        string $recipientEmail,
        string $subjectRendered,
        string $bodyRendered,
        string $status,
        ?string $errorMessage,
    ): NotificationLog {
        $log = new NotificationLog();
        $log->setRelatedOrder(null);
        $log->setNotificationRule($rule);
        $log->setEventKey($eventKey);
        $log->setRecipientType($recipientType);
        $log->setRecipientEmail($recipientEmail);
        $log->setSubjectRendered($subjectRendered);
        $log->setBodyRendered($bodyRendered);
        $log->setAttachmentType(null);
        $log->setStatus($status);
        $log->setErrorMessage($errorMessage);
        if ($status === NotificationLogStatus::SENT) {
            $log->setSentAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        }
        $this->em->persist($log);

        return $log;
    }
}

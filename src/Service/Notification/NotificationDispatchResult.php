<?php

declare(strict_types=1);

namespace App\Service\Notification;

/**
 * Outcome of dispatching all active rules for one event.
 */
final class NotificationDispatchResult
{
    public function __construct(
        private readonly int $matchedRuleCount = 0,
    ) {
    }

    private int $sent = 0;

    private int $failed = 0;

    private int $skipped = 0;

    private ?string $firstError = null;

    public function getMatchedRuleCount(): int
    {
        return $this->matchedRuleCount;
    }

    public function recordSent(): void
    {
        ++$this->sent;
    }

    public function recordFailed(?string $message = null): void
    {
        ++$this->failed;
        if ($this->firstError === null && $message !== null && $message !== '') {
            $this->firstError = $message;
        }
    }

    public function recordSkipped(): void
    {
        ++$this->skipped;
    }

    public function getSentCount(): int
    {
        return $this->sent;
    }

    public function getFailedCount(): int
    {
        return $this->failed;
    }

    public function getSkippedCount(): int
    {
        return $this->skipped;
    }

    public function getFirstError(): ?string
    {
        return $this->firstError;
    }

}

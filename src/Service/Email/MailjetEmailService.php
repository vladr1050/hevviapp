<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains
 * the property of SIA SLYFOX, its suppliers and Customers,
 * if any.  The intellectual and technical concepts contained
 * herein are proprietary to SIA SLYFOX
 * its Suppliers and Customers are protected by trade secret or copyright law.
 *
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained.
 */

namespace App\Service\Email;

use App\Service\Email\Contract\EmailServiceInterface;
use Mailjet\Client;
use Mailjet\Resources;
use Psr\Log\LoggerInterface;

/**
 * Сервис для отправки email через Mailjet API
 * Реализует Single Responsibility Principle (SOLID)
 */
class MailjetEmailService implements EmailServiceInterface
{
    private Client $mailjetClient;

    public function __construct(
        private readonly string $mailjetApiKey,
        private readonly string $mailjetApiSecret,
        private readonly string $senderEmail,
        private readonly string $senderName,
        private readonly LoggerInterface $logger,
        private readonly bool $enabled = true
    ) {
        $this->mailjetClient = new Client(
            $this->mailjetApiKey,
            $this->mailjetApiSecret,
            true,
            ['version' => 'v3.1']
        );
    }

    /**
     * {@inheritdoc}
     */
    public function send(
        string $to,
        string $subject,
        string $htmlContent,
        ?string $textContent = null
    ): bool {
        if (!$this->enabled) {
            $this->logger->info('Email sending is disabled', [
                'to' => $to,
                'subject' => $subject,
            ]);
            return true;
        }

        try {
            $body = [
                'Messages' => [
                    [
                        'From' => [
                            'Email' => $this->senderEmail,
                            'Name' => $this->senderName,
                        ],
                        'To' => [
                            [
                                'Email' => $to,
                            ],
                        ],
                        'Subject' => $subject,
                        'HTMLPart' => $htmlContent,
                    ],
                ],
            ];

            if ($textContent !== null) {
                $body['Messages'][0]['TextPart'] = $textContent;
            }

            $response = $this->mailjetClient->post(Resources::$Email, ['body' => $body]);

            if ($response->success()) {
                $this->logger->info('Email sent successfully via Mailjet', [
                    'to' => $to,
                    'subject' => $subject,
                    'message_id' => $response->getData()[0]['To'][0]['MessageID'] ?? null,
                ]);

                return true;
            }

            $this->logger->error('Failed to send email via Mailjet', [
                'to' => $to,
                'subject' => $subject,
                'status' => $response->getStatus(),
                'reason' => $response->getReasonPhrase(),
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Exception while sending email via Mailjet', [
                'to' => $to,
                'subject' => $subject,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

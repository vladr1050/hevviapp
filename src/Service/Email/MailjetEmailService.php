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
            $this->logger->notice('Mailjet: sending disabled (MAILJET_ENABLED=false); email not sent to provider', [
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
                $meta = self::extractSendMetadata($response->getBody());
                $this->logger->info('Email sent successfully via Mailjet', [
                    'to' => $to,
                    'subject' => $subject,
                    ...$meta,
                ]);

                return true;
            }

            $this->logger->error('Failed to send email via Mailjet', [
                'to' => $to,
                'subject' => $subject,
                'status' => $response->getStatus(),
                'reason' => $response->getReasonPhrase(),
                'mailjet_body' => $response->getBody(),
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

    public function sendWithPdfAttachment(
        string $to,
        string $subject,
        string $htmlContent,
        ?string $textContent,
        string $attachmentFilename,
        string $pdfBinary,
    ): bool {
        if (!$this->enabled) {
            $this->logger->notice('Mailjet: sending disabled (MAILJET_ENABLED=false); PDF email not sent to provider', [
                'to' => $to,
                'subject' => $subject,
            ]);

            return true;
        }

        try {
            $message = [
                'From' => [
                    'Email' => $this->senderEmail,
                    'Name' => $this->senderName,
                ],
                'To' => [
                    ['Email' => $to],
                ],
                'Subject' => $subject,
                'HTMLPart' => $htmlContent,
                'Attachments' => [
                    [
                        'ContentType' => 'application/pdf',
                        'Filename' => $attachmentFilename,
                        'Base64Content' => base64_encode($pdfBinary),
                    ],
                ],
            ];

            if ($textContent !== null) {
                $message['TextPart'] = $textContent;
            }

            $body = ['Messages' => [$message]];

            $response = $this->mailjetClient->post(Resources::$Email, ['body' => $body]);

            if ($response->success()) {
                $meta = self::extractSendMetadata($response->getBody());
                $this->logger->info('Email with PDF sent via Mailjet', [
                    'to' => $to,
                    'subject' => $subject,
                    ...$meta,
                ]);

                return true;
            }

            $this->logger->error('Mailjet PDF email failed', [
                'to' => $to,
                'status' => $response->getStatus(),
                'reason' => $response->getReasonPhrase(),
                'mailjet_body' => $response->getBody(),
            ]);

            return false;
        } catch (\Exception $e) {
            $this->logger->error('Mailjet PDF email exception', [
                'to' => $to,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * v3.1 /send returns { "Messages": [ { "Status", "To": [ { "MessageID", "MessageUUID", "MessageHref" } ] } ] }.
     * getData()[0] is wrong — там нет числового индекса, только ключ Messages.
     *
     * @param array<string, mixed> $body Decoded JSON from Mailjet Response::getBody()
     *
     * @return array{message_id: string|int|null, message_uuid: ?string, message_href: ?string, send_status: ?string}
     */
    private static function extractSendMetadata(array $body): array
    {
        $messages = $body['Messages'] ?? null;
        if (!\is_array($messages) || $messages === []) {
            return [
                'message_id' => null,
                'message_uuid' => null,
                'message_href' => null,
                'send_status' => null,
            ];
        }

        $first = $messages[0];
        if (!\is_array($first)) {
            return [
                'message_id' => null,
                'message_uuid' => null,
                'message_href' => null,
                'send_status' => null,
            ];
        }

        $to0 = $first['To'][0] ?? null;

        return [
            'message_id' => \is_array($to0) ? ($to0['MessageID'] ?? null) : null,
            'message_uuid' => \is_array($to0) ? ($to0['MessageUUID'] ?? null) : null,
            'message_href' => \is_array($to0) ? ($to0['MessageHref'] ?? null) : null,
            'send_status' => $first['Status'] ?? null,
        ];
    }
}

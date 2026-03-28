<?php

namespace WSms\Messaging\Gateway\Email;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsOptOutDetection;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

class MailtrapGateway extends AbstractProvider implements SupportsStatusCallback, SupportsOptOutDetection
{
    private const TRANSACTIONAL_BASE = 'https://send.api.mailtrap.io';
    private const BULK_BASE = 'https://bulk.api.mailtrap.io';

    private const EVENT_MAP = [
        'delivery'    => ['status' => 'delivered', 'permanent' => false, 'complaint' => false, 'unsubscribe' => false],
        'bounce'      => ['status' => 'failed',    'permanent' => true,  'complaint' => false, 'unsubscribe' => false],
        'soft bounce' => ['status' => 'failed',    'permanent' => false, 'complaint' => false, 'unsubscribe' => false],
        'reject'      => ['status' => 'failed',    'permanent' => true,  'complaint' => false, 'unsubscribe' => false],
        'suspension'  => ['status' => 'failed',    'permanent' => true,  'complaint' => false, 'unsubscribe' => false],
        'spam'        => ['status' => 'failed',    'permanent' => false, 'complaint' => true,  'unsubscribe' => false],
        'unsubscribe' => ['status' => 'failed',    'permanent' => false, 'complaint' => false, 'unsubscribe' => true],
    ];

    public function getId(): string
    {
        return 'mailtrap';
    }

    public function getName(): string
    {
        return 'Mailtrap';
    }

    public function getSupportedChannels(): array
    {
        return ['email'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_token' => [
                    'type'        => 'secret',
                    'label'       => __('API Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Mailtrap API token from the API Tokens page', 'wp-sms'),
                ],
                'webhook_secret' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Secret', 'wp-sms'),
                    'required'    => false,
                    'description' => __('32-character hex HMAC key for webhook signature verification', 'wp-sms'),
                ],
            ],
            'channels' => [
                'email' => [
                    'from_email' => [
                        'type'        => 'string',
                        'label'       => __('From Email', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Sender email address (must be from a verified domain)', 'wp-sms'),
                        'placeholder' => 'noreply@yourdomain.com',
                    ],
                    'from_name' => [
                        'type'        => 'string',
                        'label'       => __('From Name', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Sender display name', 'wp-sms'),
                        'placeholder' => 'Your Site Name',
                    ],
                ],
            ],
        ];
    }

    public function getMetadata(): array
    {
        return [
            'description' => __('Email delivery platform for transactional and bulk email', 'wp-sms'),
            'website'     => 'https://mailtrap.io',
            'setup_notes' => [
                __('Verify your sending domain in the Mailtrap dashboard.', 'wp-sms'),
                __('Create an API token with sending permissions at Settings > API Tokens.', 'wp-sms'),
                __('For delivery tracking, configure a webhook pointing to the callback URL shown below.', 'wp-sms'),
                __('This plugin sends all emails (including campaigns) through Mailtrap\'s Email Sending API. This is separate from Mailtrap\'s built-in campaign feature.', 'wp-sms'),
            ],
        ];
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'delivery_receipt' => true,
            'test_connection'  => true,
        ]);
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $fromEmail = $this->getChannelConfig('email', 'from_email');
        $fromName = $this->getChannelConfig('email', 'from_name');
        $headers = $this->authHeaders();

        if (!$headers || !$fromEmail) {
            return DeliveryResult::failed(__('Mailtrap credentials not configured', 'wp-sms'));
        }

        $baseUrl = $message->getCampaignId() !== null ? self::BULK_BASE : self::TRANSACTIONAL_BASE;

        $meta = $message->getMeta();
        $from = ['email' => $fromEmail];
        if ($fromName) {
            $from['name'] = $fromName;
        }

        $payload = [
            'from'    => $from,
            'to'      => [['email' => $message->getRecipient()]],
            'subject' => $meta['subject'] ?? '',
            'html'    => $message->getBody(),
            'text'    => strip_tags($message->getBody()),
        ];

        // Pass through custom headers (e.g. List-Unsubscribe)
        $emailHeaders = [];
        foreach ($meta['headers'] ?? [] as $header) {
            if (is_string($header) && str_contains($header, ':')) {
                [$name, $value] = explode(':', $header, 2);
                $name = trim($name);
                if ($name !== 'Content-Type') {
                    $emailHeaders[$name] = trim($value);
                }
            }
        }
        if (!empty($emailHeaders)) {
            $payload['headers'] = $emailHeaders;
        }

        $result = $this->httpPost("{$baseUrl}/api/send", [
            'headers' => $headers,
            'body'    => wp_json_encode($payload),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true) ?? [];

        if ($result['code'] === 200 && !empty($data['success'])) {
            $messageId = $data['message_ids'][0] ?? null;
            return DeliveryResult::sent($messageId);
        }

        if ($result['code'] === 429) {
            return DeliveryResult::failed(__('Rate limited', 'wp-sms'), retryable: true);
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            $error = $result['code'] === 401
                ? __('Invalid API token', 'wp-sms')
                : __('API token lacks sending permissions', 'wp-sms');
            return DeliveryResult::failed($error);
        }

        $errors = $data['errors'] ?? [];
        $errorMessage = !empty($errors)
            ? implode('; ', $errors)
            : ($data['message'] ?? "HTTP {$result['code']}");

        return DeliveryResult::failed($errorMessage, meta: [
            'mailtrap_errors' => $errors,
        ]);
    }

    public function testConnection(): TestConnectionResult
    {
        $headers = $this->authHeaders();

        if (!$headers) {
            return TestConnectionResult::error(__('API Token is required', 'wp-sms'));
        }

        $result = $this->httpPost(self::TRANSACTIONAL_BASE . '/api/send', [
            'headers' => $headers,
            'body'    => '{}',
        ]);

        // Provider-specific codes before common validation
        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401) {
                return TestConnectionResult::error(__('Invalid API token', 'wp-sms'));
            }
            if ($result['code'] === 403) {
                return TestConnectionResult::error(__('API token lacks sending permissions', 'wp-sms'));
            }
            // 422 = valid token, invalid payload — confirms connectivity
            if ($result['code'] === 422) {
                return TestConnectionResult::ok(__('Connected to Mailtrap successfully', 'wp-sms'));
            }
        }

        // Common: network errors, 429, unexpected codes
        $validated = $this->validateTestResponse($result, 'Mailtrap');

        return $validated instanceof TestConnectionResult
            ? $validated
            : TestConnectionResult::ok(__('Connected to Mailtrap successfully', 'wp-sms'));
    }

    // --- SupportsStatusCallback ---

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        $webhookSecret = $this->getSharedConfig('webhook_secret');
        if (!$webhookSecret) {
            return false;
        }

        $signature = $request->get_header('Mailtrap-Signature');
        if (empty($signature)) {
            return false;
        }

        $rawBody = $request->get_body();
        $expected = hash_hmac('sha256', $rawBody, $webhookSecret);

        return hash_equals($expected, $signature);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $body = json_decode($request->get_body(), true);
        $events = $body['events'] ?? [];
        $updates = [];

        foreach ($events as $event) {
            $eventType = $event['event'] ?? null;
            $messageId = $event['message_id'] ?? null;

            if (!$eventType || !$messageId) {
                continue;
            }

            $mapping = self::EVENT_MAP[$eventType] ?? null;
            if (!$mapping) {
                continue;
            }

            $updates[] = new StatusUpdate(
                providerId: $messageId,
                status: $mapping['status'],
                permanent: $mapping['permanent'],
                complaint: $mapping['complaint'],
                unsubscribe: $mapping['unsubscribe'],
            );
        }

        return $updates;
    }

    public function getStatusCallbackUrl(): string
    {
        return rest_url('wsms/v1/callbacks/mailtrap/status');
    }

    // --- Internal ---

    private function authHeaders(): ?array
    {
        $apiToken = trim($this->getSharedConfig('api_token', ''));
        if (!$apiToken) {
            return null;
        }

        return [
            'Authorization' => "Bearer {$apiToken}",
            'Content-Type'  => 'application/json',
        ];
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        foreach ($result->meta['mailtrap_errors'] ?? [] as $error) {
            if (is_string($error) && stripos($error, 'suppress') !== false) {
                return true;
            }
        }

        return false;
    }
}

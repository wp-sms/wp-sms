<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Textplode (textplode.com) — UK SMS aggregator with a narrow v3 HTTP API.
 *
 * Auth is a body-only `api_key` field on every request (no Bearer / Basic —
 * `Authorization` headers are rejected with 403 by the server).
 *
 * Send:    POST https://api.textplode.com/v3/messages/send
 * Credits: POST https://api.textplode.com/v3/account/get/credits
 *
 * No optional interfaces (SupportsStatusCallback, SupportsInboundMessage,
 * SupportsTemplates, SupportsOptOutDetection, SupportsDynamicOptions) —
 * Textplode's public docs cover only sending and balance, with no documented
 * webhook URLs, payload fields, signature scheme, template endpoints, or
 * opt-out error codes. Provider docs explicitly state Unicode is unsupported,
 * so non-ASCII bodies are passed through as-is and any failure is the
 * provider's responsibility.
 */
class TextplodeProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.textplode.com/v3';

    public function getId(): string
    {
        return 'textplode';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generate from your Textplode account → API Settings.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'MyBrand',
                        'description' => __('Alphanumeric Sender ID (≤11 characters, no spaces). UK regulatory rules apply.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key');
        $from   = (string) $this->getChannelConfig('sms', 'from');

        if ($apiKey === '' || $from === '') {
            return DeliveryResult::failed(__('Textplode credentials not configured', 'wp-sms'));
        }

        $body = [
            'api_key'    => $apiKey,
            'message'    => $message->getBody(),
            'from'       => $from,
            'recipients' => [
                ['phone_number' => ltrim($message->getRecipient(), '+')],
            ],
        ];

        $result = $this->httpPost(self::API_BASE . '/messages/send', [
            'headers' => $this->jsonHeaders(),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Textplode API key', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        // Textplode wraps errors in {errors: {errorMessage, errorCode}} even
        // on 200 responses, so check the envelope before the HTTP code.
        $errorMessage = $this->extractErrorMessage($data);
        if ($errorMessage !== null) {
            $errorCode = $data['errors']['errorCode'] ?? null;
            return DeliveryResult::failed(
                $errorMessage,
                meta: array_filter(['textplode_code' => $errorCode]),
            );
        }

        if ($result['code'] >= 200 && $result['code'] < 300 && is_array($data)) {
            $messageId = $data['data'][0]['message_ids'][0] ?? null;
            return DeliveryResult::sent(
                providerId: $messageId !== null ? (string) $messageId : null,
            );
        }

        return DeliveryResult::failed(
            sprintf(__('Textplode send failed (HTTP %d)', 'wp-sms'), $result['code']),
        );
    }

    public function getCredit(): ?string
    {
        $apiKey = (string) $this->getSharedConfig('api_key');
        if ($apiKey === '') {
            return null;
        }

        $result = $this->httpPost(self::API_BASE . '/account/get/credits', [
            'headers' => $this->jsonHeaders(),
            'body'    => wp_json_encode(['api_key' => $apiKey]),
        ]);

        if ($result instanceof DeliveryResult || $result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['credits'])) {
            return null;
        }

        return (string) $data['credits'];
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key');
        if ($apiKey === '') {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/account/get/credits', [
            'headers' => $this->jsonHeaders(),
            'body'    => wp_json_encode(['api_key' => $apiKey]),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid API Key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Textplode');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $errorMessage = $this->extractErrorMessage($data);
        if ($errorMessage !== null) {
            return TestConnectionResult::error($errorMessage);
        }

        $balance = $data['credits'] ?? 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s credits', 'wp-sms'), $balance),
            ['balance' => $balance],
        );
    }

    /**
     * Pull the human-readable error message out of Textplode's error envelope.
     * Returns null if the response is not an error (success responses use an
     * empty `errors` object/array).
     */
    private function extractErrorMessage(mixed $data): ?string
    {
        if (!is_array($data) || empty($data['errors']) || !is_array($data['errors'])) {
            return null;
        }
        $message = $data['errors']['errorMessage'] ?? null;
        return is_string($message) && $message !== '' ? $message : null;
    }

    private function jsonHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }
}

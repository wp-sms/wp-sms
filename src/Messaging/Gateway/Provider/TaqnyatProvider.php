<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Taqnyat (taqnyat.sa) — Saudi-focused premium SMS gateway.
 *
 * API contract verified against the official Taqnyat SDKs (PHP and Python,
 * latest push 2025-10-01) at github.com/taqnyat:
 *   Send:    POST https://api.taqnyat.sa/v1/messages
 *            { "recipients": ["9665…"], "sender": "<senderId>",
 *              "body": "<text>", "scheduled": "<ISO8601>" } (scheduled optional)
 *   Balance: GET  https://api.taqnyat.sa/account/balance
 *   Senders: GET  https://api.taqnyat.sa/v1/messages/senders
 * Auth:    Authorization: Bearer <token from Portal → Developer → Applications>
 *
 * Documented error codes (docs.taqnyat.sa/support/error-code-reference.md):
 *   2   Zero balance         3   Insufficient balance
 *   14  Invalid sender       15  Invalid recipients
 *   101 API disabled         102 IP not authorized
 *   104 Invalid bearer token (mapped to auth-error in testConnection)
 *
 * Out of scope (not surfaced by the public SDK): MMS/media, flash SMS,
 * delivery receipts (no documented DLR webhook spec), inbound MO,
 * opt-out detection, list-templates, regulatory IDs (Saudi market — no DLT/MIIT).
 */
class TaqnyatProvider extends AbstractProvider implements SupportsDynamicOptions
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.taqnyat.sa';

    // TODO(channels): Taqnyat markets WhatsApp and Voice on dev.taqnyat.sa,
    // but neither is documented in the public SDK — keep SMS-only until the
    // endpoints/payload shapes are published.
    // TODO(verify): A "Verify" product is marketed but no public endpoint
    // exists; defer until we can implement SupportsVerify against a real spec.

    public function getId(): string
    {
        return 'taqnyat';
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
                    'required'    => true,
                    'label'       => __('API Key', 'wp-sms'),
                    'description' => __('Bearer token generated under Portal → Developer → Applications at taqnyat.sa.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'required'    => true,
                        'label'       => __('Sender ID', 'wp-sms'),
                        'description' => __('Approved sender ID from your Taqnyat account. Contact support@taqnyat.sa to register a new sender.', 'wp-sms'),
                        'dynamic'     => true,
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        $sender = (string) $this->getChannelConfig('sms', 'from', '');

        if ($apiKey === '' || $sender === '') {
            return DeliveryResult::failed(__('Taqnyat credentials not configured', 'wp-sms'));
        }

        $body = [
            'recipients' => [ltrim($message->getRecipient(), '+')],
            'sender'     => $sender,
            'body'       => $message->getBody(),
        ];

        $result = $this->httpPost(self::API_BASE . '/v1/messages', [
            'headers' => $this->authHeaders($apiKey),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        $code = (int) $result['code'];

        if ($code === 401 || $code === 403) {
            return DeliveryResult::failed(__('Invalid Taqnyat bearer token', 'wp-sms'));
        }

        if ($code >= 200 && $code < 300) {
            $providerId = $this->extractProviderId($data);
            return DeliveryResult::sent(providerId: $providerId);
        }

        return DeliveryResult::failed($this->extractErrorMessage($data, $code));
    }

    public function getCredit(): ?string
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/account/balance', [
            'headers' => $this->authHeaders($apiKey),
        ]);
        if ($result instanceof DeliveryResult) {
            return null;
        }

        $code = (int) $result['code'];
        if ($code < 200 || $code >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return null;
        }

        $balance = $data['balance'] ?? $data['credits'] ?? null;
        return $balance !== null ? (string) $balance : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/account/balance', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if (!$result instanceof DeliveryResult) {
            $code = (int) $result['code'];
            if ($code === 401 || $code === 403) {
                return TestConnectionResult::error(__('Invalid Taqnyat bearer token', 'wp-sms'));
            }
            // Taqnyat surfaces error code 104 (invalid bearer token) inside the
            // JSON body — map it to an auth error before the generic handler.
            $data = json_decode($result['body'], true);
            if (is_array($data) && (int) ($data['statusCode'] ?? $data['code'] ?? 0) === 104) {
                return TestConnectionResult::error(__('Invalid Taqnyat bearer token', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Taqnyat');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = (string) ($data['balance'] ?? $data['credits'] ?? 'N/A');
        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s', 'wp-sms'), $balance),
            ['balance' => $balance],
        );
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'from' || $section !== 'sms') {
            return [];
        }

        return $this->withConfig($config, function () {
            $apiKey = (string) $this->getSharedConfig('api_key', '');
            if ($apiKey === '') {
                return [];
            }

            try {
                $data = $this->fetchJsonOrFail(self::API_BASE . '/v1/messages/senders', [
                    'headers' => $this->authHeaders($apiKey),
                ]);
            } catch (\RuntimeException) {
                return [];
            }

            $entries = $data['senders'] ?? $data['data'] ?? (isset($data[0]) ? $data : []);
            if (!is_array($entries)) {
                return [];
            }

            $options = [];
            foreach ($entries as $entry) {
                $name = is_array($entry)
                    ? ($entry['sender'] ?? $entry['name'] ?? $entry['senderId'] ?? null)
                    : (is_string($entry) ? $entry : null);
                if (!is_string($name) || $name === '') {
                    continue;
                }
                $options[] = ['value' => $name, 'label' => $name];
            }
            return $options;
        });
    }

    // --- Internal ---

    private function authHeaders(string $apiKey): array
    {
        return [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
        ];
    }

    private function extractProviderId(mixed $data): ?string
    {
        if (!is_array($data)) {
            return null;
        }
        foreach (['messageId', 'message_id', 'id', 'reference', 'refId'] as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                return (string) $data[$key];
            }
        }
        if (isset($data['data']) && is_array($data['data'])) {
            return $this->extractProviderId($data['data']);
        }
        if (isset($data[0]) && is_array($data[0])) {
            return $this->extractProviderId($data[0]);
        }
        return null;
    }

    private function extractErrorMessage(mixed $data, int $statusCode): string
    {
        if (is_array($data)) {
            foreach (['message', 'error', 'error_description', 'description', 'statusMessage'] as $key) {
                if (isset($data[$key])) {
                    if (is_array($data[$key])) {
                        return (string) ($data[$key][0] ?? sprintf('HTTP %d', $statusCode));
                    }
                    return (string) $data[$key];
                }
            }
        }
        return sprintf('HTTP %d', $statusCode);
    }
}

<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Deewan — Saudi Arabian CPaaS (deewan.sa, Riyadh) for SMS in KSA / GCC.
 *
 * API: https://apis.deewan.sa
 *   1. POST /auth/v1/signin   { userName, apiKey }   → { data.access_token }
 *   2. POST /sms/v1/messages  { messageText, senderName, messageType, recipients }
 *      Bearer <access_token>
 *   3. GET  /sms/v1/account/balance                   → { data.Account.Credit }
 *
 * messageType auto-detects 'text' (GSM-7 / ASCII) vs 'unicode' (Arabic etc).
 */
final class DeewanProvider extends AbstractProvider
{
    public const TESTED = false;

    private const API_BASE = 'https://apis.deewan.sa';

    // TODO(verify): Deewan offers MFA verify-as-a-service; defer until SupportsVerify interface lands.
    // TODO(dlr): Deewan supports per-message dlrUrl callbacks (no HMAC); revisit if WSMS adds per-send callback URL injection.

    public function getId(): string
    {
        return 'deewan';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'username' => [
                    'type'        => 'string',
                    'label'       => __('Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Deewan account username from console.deewan.sa.', 'wp-sms'),
                ],
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generate from console.deewan.sa under your account API settings.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_name' => [
                        'type'        => 'string',
                        'label'       => __('Sender Name', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'COMPANY',
                        'description' => __('CST-pre-registered alphanumeric sender ID (3–11 chars; promotional IDs require an "-AD" suffix per CST rules).', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $apiKey   = (string) $this->getSharedConfig('api_key', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender_name', '');

        if ($username === '' || $apiKey === '' || $sender === '') {
            return DeliveryResult::failed(__('Deewan credentials not configured', 'wp-sms'));
        }

        $token = $this->getAccessToken($username, $apiKey);
        if ($token instanceof DeliveryResult) {
            return $token;
        }

        $result = $this->httpPost(self::API_BASE . '/sms/v1/messages', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body' => json_encode([
                'messageText' => $message->getBody(),
                'senderName'  => $sender,
                'messageType' => $this->detectMessageType($message->getBody()),
                'recipients'  => ltrim($message->getRecipient(), '+'),
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        $code = (int) $result['code'];

        if ($code >= 200 && $code < 300) {
            $providerId = $data['requestStatus']['RequestID']
                ?? $data['data']['messageId']
                ?? null;
            return DeliveryResult::sent(providerId: $providerId !== null ? (string) $providerId : null);
        }

        return DeliveryResult::failed($this->extractError($data, $code));
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $apiKey   = (string) $this->getSharedConfig('api_key', '');

        if ($username === '' || $apiKey === '') {
            return null;
        }

        $token = $this->getAccessToken($username, $apiKey);
        if ($token instanceof DeliveryResult) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/sms/v1/account/balance', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        $credit = $data['data']['Account']['Credit'] ?? null;

        return $credit !== null ? (string) $credit : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $apiKey   = (string) $this->getSharedConfig('api_key', '');

        if ($username === '' || $apiKey === '') {
            return TestConnectionResult::error(__('Username and API Key are required', 'wp-sms'));
        }

        $signinResult = $this->httpPost(self::API_BASE . '/auth/v1/signin', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => json_encode(['userName' => $username, 'apiKey' => $apiKey]),
        ]);

        $data = $this->validateTestResponse($signinResult, 'Deewan');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $token = $data['data']['access_token'] ?? null;
        if (!$token) {
            $message = $data['error']['description']
                ?? $this->extractError($data, 200)
                ?? __('Invalid credentials', 'wp-sms');
            return TestConnectionResult::error($message);
        }

        $balanceResult = $this->httpGet(self::API_BASE . '/sms/v1/account/balance', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ]);

        $balanceData = $this->validateTestResponse($balanceResult, 'Deewan');
        if ($balanceData instanceof TestConnectionResult) {
            return $balanceData;
        }

        $credit = (string) ($balanceData['data']['Account']['Credit'] ?? 'N/A');

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }

    /**
     * Exchange username + API key for a short-lived bearer token.
     *
     * @return string|DeliveryResult Token string on success, DeliveryResult::failed on any error.
     */
    private function getAccessToken(string $username, string $apiKey): string|DeliveryResult
    {
        $result = $this->httpPost(self::API_BASE . '/auth/v1/signin', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => json_encode(['userName' => $username, 'apiKey' => $apiKey]),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        $code = (int) $result['code'];

        if ($code < 200 || $code >= 300) {
            return DeliveryResult::failed($this->extractError($data, $code));
        }

        $token = $data['data']['access_token'] ?? null;
        if (!$token) {
            return DeliveryResult::failed($this->extractError($data, $code));
        }

        return (string) $token;
    }

    private function detectMessageType(string $body): string
    {
        // Anything outside printable ASCII + CR/LF is treated as unicode (UCS-2).
        return preg_match('/[^\x20-\x7E\r\n]/u', $body) ? 'unicode' : 'text';
    }

    /**
     * Pull a human-friendly error from a Deewan response body. The API uses
     * two shapes: `error.description` (signin) and `errors: [[code, msg]]`
     * (validation failures from the SMS endpoint).
     */
    private function extractError(?array $data, int $code): string
    {
        if (is_array($data)) {
            if (!empty($data['error']['description'])) {
                return (string) $data['error']['description'];
            }

            if (!empty($data['errors']) && is_array($data['errors'])) {
                $first = $data['errors'][0] ?? null;
                if (is_array($first)) {
                    $msg = $first[1] ?? $first['message'] ?? null;
                    if ($msg !== null) {
                        return (string) $msg;
                    }
                }
                if (is_string($first)) {
                    return $first;
                }
            }

            if (!empty($data['message'])) {
                return (string) $data['message'];
            }
        }

        return sprintf(__('Deewan error (HTTP %d)', 'wp-sms'), $code);
    }
}

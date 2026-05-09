<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * BulkSMSbd — Bangladesh-only SMS provider (bulksmsbd.net).
 *
 * Single endpoint host: https://bulksmsbd.net/api
 *   Send:    POST /smsapi          (form: api_key, senderid, number, message, type=text)
 *   Balance: POST /getBalanceApi   (form: api_key)
 *
 * Response shape is loosely documented: 202 = success, anything else =
 * provider-specific error code (1002 sender, 1007 low balance, 1011 auth,
 * 1005/1006/1013–1021 misc). The "202" can arrive as the HTTP status, a
 * JSON {response_code: 202}, or a plain-text body — so doSend() inspects
 * both the HTTP code and the decoded body before classifying.
 *
 * @todo verify — flip TESTED to true after a real send to a Bangladeshi
 *   number confirms the response shape and the https:// host works.
 *   v7's wp.bulksmsbd.com host is dead; current docs use bulksmsbd.net.
 */
class BulkSMSbdProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://bulksmsbd.net/api';

    public function getId(): string
    {
        return 'bulksmsbd';
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
                    'description' => __('API key from your BulkSMSbd dashboard.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => '8809XXXXXX',
                        'description' => __('Approved sender ID issued by BulkSMSbd.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        $sender = $this->getChannelConfig('sms', 'sender_id');

        if (!$apiKey) {
            return DeliveryResult::failed(__('BulkSMSbd API key not configured', 'wp-sms'));
        }
        if (!$sender) {
            return DeliveryResult::failed(__('BulkSMSbd Sender ID not configured', 'wp-sms'));
        }

        $number = $this->normalizeRecipient($message->getRecipient());
        if ($number === null) {
            return DeliveryResult::failed(
                sprintf(__('Invalid Bangladesh recipient number: %s', 'wp-sms'), $message->getRecipient())
            );
        }

        $result = $this->httpPost(self::API_BASE . '/smsapi', [
            'body' => [
                'api_key'  => $apiKey,
                'senderid' => $sender,
                'number'   => $number,
                'message'  => $message->getBody(),
                'type'     => 'text',
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $body = trim((string) $result['body']);
        $decoded = json_decode($body, true);
        $responseCode = is_array($decoded) ? ($decoded['response_code'] ?? null) : null;

        $isHttpSuccess = $result['code'] === 202 || ($result['code'] >= 200 && $result['code'] < 300);
        $isBodySuccess = $responseCode === 202 || $body === '202';

        if ($isBodySuccess || ($isHttpSuccess && $responseCode === null && !$this->bodyLooksLikeError($body))) {
            $providerId = is_array($decoded) ? ($decoded['message_id'] ?? $decoded['id'] ?? null) : null;
            return DeliveryResult::queued($providerId !== null ? (string) $providerId : null);
        }

        $errorCode = $responseCode !== null ? (string) $responseCode : ($body !== '' ? $body : sprintf('HTTP %d', $result['code']));
        $errorMessage = is_array($decoded) ? ($decoded['error_message'] ?? $decoded['message'] ?? null) : null;

        return DeliveryResult::failed(
            $errorMessage ? sprintf('%s (%s)', $errorMessage, $errorCode) : $errorCode,
            meta: ['bulksmsbd_response' => $body, 'bulksmsbd_code' => $errorCode],
        );
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpPost(self::API_BASE . '/getBalanceApi', [
            'body' => ['api_key' => $apiKey],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }
        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $balance = $this->parseBalance((string) $result['body']);
        return $balance !== null ? (string) $balance : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/getBalanceApi', [
            'body' => ['api_key' => $apiKey],
        ]);

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the BulkSMSbd API. Check your server\'s internet connection.', 'wp-sms')
            );
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return TestConnectionResult::error(__('Invalid BulkSMSbd API key', 'wp-sms'));
        }
        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from BulkSMSbd (HTTP %d)', 'wp-sms'), $result['code'])
            );
        }

        $body = trim((string) $result['body']);
        $balance = $this->parseBalance($body);

        if ($balance === null) {
            if ($this->bodyLooksLikeError($body)) {
                return TestConnectionResult::error(
                    sprintf(__('BulkSMSbd rejected the API key (%s)', 'wp-sms'), $body)
                );
            }
            return TestConnectionResult::ok(__('Connected to BulkSMSbd', 'wp-sms'));
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s BDT', 'wp-sms'), $balance),
            ['balance' => (string) $balance],
        );
    }

    /**
     * Strip non-digits, then ensure 880 country prefix.
     * Returns null if the result doesn't look like a Bangladesh mobile number.
     */
    private function normalizeRecipient(string $recipient): ?string
    {
        $digits = preg_replace('/\D+/', '', $recipient) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '880')) {
            return $digits;
        }

        if (str_starts_with($digits, '01')) {
            return '88' . $digits;
        }

        return null;
    }

    /**
     * The balance endpoint returns either a JSON {balance: 12.34} / {Balance: ...}
     * or a bare numeric body. Pull a float out of either shape.
     */
    private function parseBalance(string $body): ?float
    {
        $body = trim($body);
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            foreach (['balance', 'Balance', 'amount', 'credit'] as $key) {
                if (isset($decoded[$key]) && is_numeric($decoded[$key])) {
                    return (float) $decoded[$key];
                }
            }
            return null;
        }

        if (is_numeric($body)) {
            return (float) $body;
        }

        return null;
    }

    private function bodyLooksLikeError(string $body): bool
    {
        if ($body === '' || $body === '202') {
            return false;
        }

        // Provider error codes: 1002, 1005, 1006, 1007, 1011, 1013–1021
        if (preg_match('/\b10(0[2567]|1[1-9]|2[01])\b/', $body)) {
            return true;
        }

        return (bool) preg_match('/\b(error|invalid|unauthori[sz]ed|fail)/i', $body);
    }
}

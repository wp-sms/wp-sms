<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsOptOutDetection;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Unisender — Russian email-marketing platform with a transactional SMS API.
 *
 * Wire format: POST application/x-www-form-urlencoded with `api_key` carried
 * in the body on every request (no header auth). Responses opt-in to JSON via
 * `format=json`. v7 used GET with the api_key in the query string — v8 follows
 * the canonical webmasterskaya/php-unisender-api wrapper and switches to POST
 * to avoid URL-length and non-ASCII encoding issues.
 *
 * Opt-out detection: `sendSms` returns `code: "unsubscribed_globally"` when
 * the recipient has been globally unsubscribed from this account; SuppressionGuard
 * uses `isOptOutError` to flip the contact to opted-out.
 *
 * TODO: email channel deferred — Unisender requires list_id + verified sender;
 * doesn't fit WSMS's transactional model.
 * TODO: telegram channel deferred — canonical SDK exposes no transactional
 * sendTelegram; Telegram delivery in Unisender is admin-managed via subscribe
 * lists.
 */
class UnisenderProvider extends AbstractProvider implements SupportsOptOutDetection
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.unisender.com/en/api/';

    public function getId(): string
    {
        return 'unisender';
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
                    'description' => __('Find it in the Unisender dashboard under Account → Integration and API → API key.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'MyCompany',
                        'description' => __('Alphanumeric sender name (3–11 characters). Must be pre-registered and approved with Unisender under SMS → Senders.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return DeliveryResult::failed(__('Unisender API key not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'from', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('Unisender sender name not configured', 'wp-sms'));
        }

        $phone = preg_replace('/\D+/', '', $message->getRecipient()) ?? '';

        $result = $this->httpPost(self::API_BASE . 'sendSms', [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query([
                'api_key' => $apiKey,
                'phone'   => $phone,
                'sender'  => $sender,
                'text'    => $message->getBody(),
                'format'  => 'json',
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if (is_array($data) && isset($data['error'])) {
            $code = isset($data['code']) ? (string) $data['code'] : null;
            return DeliveryResult::failed(
                sprintf('Unisender: %s', (string) $data['error']),
                array_filter([
                    'unisender_error_code' => $code,
                    'unisender_http_code'  => $result['code'] ?: null,
                ]),
            );
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(sprintf('Unisender HTTP %d', $result['code']));
        }

        if (!is_array($data) || !isset($data['result'])) {
            return DeliveryResult::failed(__('Invalid response from Unisender', 'wp-sms'));
        }

        $smsId = isset($data['result']['sms_id']) ? (string) $data['result']['sms_id'] : null;
        $price = isset($data['result']['price']) ? (float) $data['result']['price'] : null;

        return DeliveryResult::sent(providerId: $smsId, cost: $price);
    }

    public function getCredit(): ?string
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return null;
        }

        $result = $this->httpPost(self::API_BASE . 'getUserInfo', [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query(['api_key' => $apiKey, 'format' => 'json']),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['result']['balance'])) {
            return null;
        }

        $balance  = (string) $data['result']['balance'];
        $currency = (string) ($data['result']['currency'] ?? '');

        return $currency !== '' ? sprintf('%s %s', $balance, $currency) : $balance;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . 'getUserInfo', [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query(['api_key' => $apiKey, 'format' => 'json']),
        ]);

        if (!$result instanceof DeliveryResult) {
            $body = json_decode($result['body'], true);
            if (is_array($body) && isset($body['error'])) {
                $code = (string) ($body['code'] ?? '');
                if ($code === 'invalid_api_key') {
                    return TestConnectionResult::error(__('Invalid Unisender API key', 'wp-sms'));
                }
                return TestConnectionResult::error(sprintf('Unisender: %s', (string) $body['error']));
            }
        }

        $data = $this->validateTestResponse($result, 'Unisender');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance  = isset($data['result']['balance']) ? (string) $data['result']['balance'] : null;
        $currency = isset($data['result']['currency']) ? (string) $data['result']['currency'] : null;

        if ($balance === null) {
            return TestConnectionResult::ok(__('Connected to Unisender', 'wp-sms'));
        }

        $display = $currency !== null && $currency !== '' ? sprintf('%s %s', $balance, $currency) : $balance;

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s', 'wp-sms'), $display),
            array_filter([
                'balance'  => $balance,
                'currency' => $currency,
            ], fn($v) => $v !== null && $v !== ''),
        );
    }

    public function getFeatures(): array
    {
        return [
            'mms'              => false,
            'flash_sms'        => false,
            'delivery_receipt' => false,
            'incoming'         => false,
            'unicode'          => true,
            'media'            => false,
            'test_connection'  => true,
        ];
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        return ($result->meta['unisender_error_code'] ?? null) === 'unsubscribed_globally';
    }
}

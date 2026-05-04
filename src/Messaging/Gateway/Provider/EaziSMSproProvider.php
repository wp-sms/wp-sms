<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

class EaziSMSproProvider extends AbstractProvider
{
    public const TESTED = true;

    private const API_URL = 'https://dashboard.eazismspro.com/sms/api';

    public function getId(): string
    {
        return 'eazismspro';
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
                    'description' => __('Generate from dashboard.eazismspro.com → Generate API.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'MyBrand',
                        'description' => __('Maximum 11 characters, alphanumeric only.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey   = $this->getSharedConfig('api_key');
        $senderId = $this->getChannelConfig('sms', 'sender_id');

        if (!$apiKey || !$senderId) {
            return DeliveryResult::failed(__('EaziSMSpro credentials not configured', 'wp-sms'));
        }

        $params = http_build_query([
            'action'   => 'send-sms',
            'api_key'  => $apiKey,
            'from'     => $senderId,
            'sms'      => $message->getBody(),
            'to'       => $message->getRecipient(),
            'response' => 'json',
        ]);

        $result = $this->httpGet(self::API_URL . '?' . $params);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $decoded = json_decode($result['body'], true);
        $code    = is_array($decoded) && isset($decoded['code']) ? (string) $decoded['code'] : '';

        // Live API uses 3-digit codes (e.g. 100); legacy/public docs use 4-digit (e.g. 1000).
        if ($code === '100' || $code === '1000') {
            return DeliveryResult::sent();
        }

        if (is_array($decoded) && !empty($decoded['message'])) {
            return DeliveryResult::failed((string) $decoded['message']);
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(
                sprintf(__('EaziSMSpro returned HTTP %d', 'wp-sms'), $result['code'])
            );
        }

        return DeliveryResult::failed(
            sprintf(__('EaziSMSpro error: %s', 'wp-sms'), $code !== '' ? $code : 'unknown')
        );
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $params = http_build_query([
            'action'   => 'check-balance',
            'api_key'  => $apiKey,
            'response' => 'json',
        ]);

        $result = $this->httpGet(self::API_URL . '?' . $params);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $decoded = json_decode($result['body'], true);
        if (!is_array($decoded) || !isset($decoded['balance'])) {
            return null;
        }

        return (string) $decoded['balance'];
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $params = http_build_query([
            'action'   => 'check-balance',
            'api_key'  => $apiKey,
            'response' => 'json',
        ]);

        $result = $this->httpGet(self::API_URL . '?' . $params);

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the EaziSMSpro API. Check your server\'s internet connection.', 'wp-sms')
            );
        }

        $decoded = json_decode($result['body'], true);

        // The API returns balance on success regardless of HTTP status framing.
        if (is_array($decoded) && isset($decoded['balance'])) {
            $balance = (string) $decoded['balance'];
            return TestConnectionResult::ok(
                sprintf(__('Connected — Credit: %s', 'wp-sms'), $balance),
                ['balance' => $balance],
            );
        }

        // Surface the API's own error message when present (e.g. "Authentication Failed").
        if (is_array($decoded) && !empty($decoded['message'])) {
            return TestConnectionResult::error((string) $decoded['message']);
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from EaziSMSpro (HTTP %d)', 'wp-sms'), $result['code'])
            );
        }

        return TestConnectionResult::error(__('Invalid response from EaziSMSpro', 'wp-sms'));
    }
}

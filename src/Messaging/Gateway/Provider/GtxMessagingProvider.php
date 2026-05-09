<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * GTX Messaging — German aggregator (operated by Message Mobile GmbH).
 *
 * SMS-only via the REST `sendsms` endpoint. The UUID API key is embedded in the
 * request path, not sent as a header.
 */
class GtxMessagingProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_URL = 'https://rest.gtx-messaging.net/smsc/sendsms/%s/json';

    // TODO(verify): GTX exposes /smspin/request + /smspin/verify; defer until SupportsVerify lands.

    public function getId(): string
    {
        return 'gtxmessaging';
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
                    'placeholder' => 'aaaaaaaa-bbbb-cccc-dddd-1234567890ab',
                    'description' => __('UUID API key issued in your GTX customer portal. Embedded in the request path; not a header.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'YourBrand',
                        'description' => __('Alphanumeric (≤11 chars), shortcode, or E.164 number with leading +. Sender approvals are managed by your GTX account manager.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        $from = $this->getChannelConfig('sms', 'from');

        if (!$apiKey || !$from) {
            return DeliveryResult::failed(__('GTX Messaging credentials not configured', 'wp-sms'));
        }

        $url = sprintf(self::SEND_URL, rawurlencode($apiKey));

        $result = $this->httpPost($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode([
                'from' => $from,
                'to'   => $message->getRecipient(),
                'text' => $message->getBody(),
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        $code = $result['code'];

        if ($code === 401 || $code === 403) {
            return DeliveryResult::failed(__('Invalid GTX API key', 'wp-sms'));
        }

        if ($code === 400) {
            return DeliveryResult::failed(
                $this->extractFieldError($data) ?? __('Validation failed', 'wp-sms'),
                meta: array_filter([
                    'provider_status' => is_array($data) ? ($data['message-status'] ?? null) : null,
                ]),
            );
        }

        if ($code >= 200 && $code < 300 && is_array($data)
            && ($data['message-status'] ?? null) === 'OK'
            && !empty($data['message-id'])
        ) {
            return DeliveryResult::queued((string) $data['message-id']);
        }

        return DeliveryResult::failed(
            "HTTP {$code}",
            meta: array_filter([
                'provider_status' => is_array($data) ? ($data['message-status'] ?? null) : null,
            ]),
        );
    }

    public function getCredit(): ?string
    {
        // GTX exposes account balance on a separate user/pass HTTP API outside this class's auth scope.
        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        // POST a deliberately invalid payload — auth is checked before validation,
        // so a 400 response means the key is good and only the body was rejected.
        $url = sprintf(self::SEND_URL, rawurlencode($apiKey));
        $result = $this->httpPost($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode([
                'from' => '0',
                'to'   => '+0',
                'text' => '',
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return $this->validateTestResponse($result, 'GTX Messaging');
        }

        $code = $result['code'];

        if ($code === 401 || $code === 403) {
            return TestConnectionResult::error(__('Invalid GTX API key', 'wp-sms'));
        }

        if ($code === 400) {
            return TestConnectionResult::ok(__('API key accepted', 'wp-sms'));
        }

        return TestConnectionResult::error(
            sprintf(__('Unexpected response: HTTP %d', 'wp-sms'), $code),
        );
    }

    private function extractFieldError(mixed $data): ?string
    {
        if (!is_array($data)) {
            return null;
        }

        foreach ($data as $key => $value) {
            if ($key === 'message-status' || $key === 'message-id' || $key === 'message-count') {
                continue;
            }
            if (is_array($value) && isset($value[0]) && is_string($value[0])) {
                return sprintf('%s: %s', $key, $value[0]);
            }
            if (is_string($value)) {
                return $value;
            }
        }

        return null;
    }
}

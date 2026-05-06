<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * SignalAds — Iranian SMS gateway with a REST API at panel.signalads.com.
 *
 *   Send:    GET  http://panel.signalads.com/rest/api/v1/message/send.json
 *            body: { numbers, from, message }
 *   Credit:  GET  http://panel.signalads.com/rest/api/v1/user/credit.json
 *
 * Auth: Bearer API key (Authorization: Bearer <api_key>).
 * Response shape: { success, data: { ... } } — success=true indicates success;
 * credit lives at data.credit.
 *
 * Out of scope (not exposed by the API): MMS, flash SMS, delivery webhooks,
 * inbound webhooks, template/pattern messaging, and opt-out detection.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class SignalAdsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'http://panel.signalads.com/rest/api/v1';

    public function getId(): string
    {
        return 'signalads';
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
                    'description' => __('Your SignalAds API key from the panel', 'wp-sms'),
                    'placeholder' => 'Your SignalAds API key',
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Approved sender number from your SignalAds account', 'wp-sms'),
                        'placeholder' => '+XXXXXXXXXXXXX',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return DeliveryResult::failed(__('SignalAds API key not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('SignalAds sender not configured', 'wp-sms'));
        }

        $url = add_query_arg([
            'numbers' => $message->getRecipient(),
            'from'    => $sender,
            'message' => $message->getBody(),
        ], self::API_BASE . '/message/send.json');

        $result = $this->httpGet($url, [
            'headers' => $this->authHeaders($apiKey),
        ]);

        return $this->parseResponse($result);
    }

    private function parseResponse(array|DeliveryResult $result): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(__('Invalid response from SignalAds', 'wp-sms'));
        }

        if (!($data['success'] ?? false)) {
            $error = $data['message'] ?? $data['error'] ?? __('SignalAds send failed', 'wp-sms');
            return DeliveryResult::failed((string) $error);
        }

        $messageId = $data['data']['id'] ?? $data['data']['messageId'] ?? null;
        return DeliveryResult::sent($messageId !== null ? (string) $messageId : null);
    }

    public function getCredit(): ?string
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/user/credit.json', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !($data['success'] ?? false)) {
            return null;
        }

        $credit = $data['data']['credit'] ?? null;
        return $credit !== null ? (string) $credit : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/user/credit.json', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'SignalAds');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (!($data['success'] ?? false)) {
            $message = $data['message'] ?? $data['error'] ?? __('Unknown error', 'wp-sms');
            return TestConnectionResult::error((string) $message);
        }

        $credit = (string) ($data['data']['credit'] ?? 'N/A');
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }

    private function authHeaders(string $apiKey): array
    {
        return [
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept'        => 'application/json',
        ];
    }
}

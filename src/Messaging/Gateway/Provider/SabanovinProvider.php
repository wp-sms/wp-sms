<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Sabanovin — Iranian SMS gateway with a JSON REST API keyed by an API token.
 *
 * Endpoints (the api_key is part of the path, not a header):
 *   Send:   GET http://api.sabanovin.com/v1/{api_key}/sms/send.json
 *           ?gateway=&text=&to=
 *   Credit: GET http://api.sabanovin.com/v1/{api_key}/credit.json
 *
 * Auth: per-account API key, embedded in the URL path.
 *
 * Response shape: { status: { code: 200, message }, entries|entry: {...} }
 *
 * Out of scope: templates/patterns (not exposed by this REST API), MMS, flash SMS,
 * status webhooks, inbound webhooks, opt-out detection.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class SabanovinProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'http://api.sabanovin.com/v1';

    public function getId(): string
    {
        return 'sabanovin';
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
                    'description' => __('Your Sabanovin API key (issued in the panel under Developer Settings)', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Gateway / Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line (gateway) purchased from the Sabanovin panel', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        $sender = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($apiKey === '') {
            return DeliveryResult::failed(__('Sabanovin API key not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('Sabanovin sender not configured', 'wp-sms'));
        }

        $url = self::API_BASE . '/' . rawurlencode($apiKey) . '/sms/send.json?' . http_build_query([
            'gateway' => $sender,
            'text'    => $message->getBody(),
            'to'      => $message->getRecipient(),
        ]);

        $result = $this->httpGet($url);
        return $this->parseResponse($result);
    }

    private function parseResponse(array|DeliveryResult $result): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(__('Invalid response from Sabanovin', 'wp-sms'));
        }

        $statusCode = (int) ($data['status']['code'] ?? 0);
        if ($statusCode !== 200) {
            $message = (string) ($data['status']['message'] ?? __('Sabanovin send failed', 'wp-sms'));
            return DeliveryResult::failed($message);
        }

        // entries can be a list of message ids or a single object — pick the first scalar id we see.
        $providerId = '';
        $entries = $data['entries'] ?? null;
        if (is_array($entries)) {
            $first = reset($entries);
            if (is_scalar($first)) {
                $providerId = (string) $first;
            } elseif (is_array($first) && isset($first['id'])) {
                $providerId = (string) $first['id'];
            }
        } elseif (is_scalar($entries)) {
            $providerId = (string) $entries;
        }

        return DeliveryResult::sent($providerId);
    }

    public function getCredit(): ?string
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/' . rawurlencode($apiKey) . '/credit.json');
        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || (int) ($data['status']['code'] ?? 0) !== 200) {
            return null;
        }

        $credit = $data['entry']['credit'] ?? null;
        return $credit !== null ? (string) $credit : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/' . rawurlencode($apiKey) . '/credit.json');

        $data = $this->validateTestResponse($result, 'Sabanovin');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if ((int) ($data['status']['code'] ?? 0) !== 200) {
            $message = (string) ($data['status']['message'] ?? __('Unknown error', 'wp-sms'));
            return TestConnectionResult::error($message);
        }

        $credit = (string) ($data['entry']['credit'] ?? 'N/A');
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }
}

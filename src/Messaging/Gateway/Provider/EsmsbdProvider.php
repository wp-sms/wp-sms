<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * eSMS Bangladesh — Bangladesh-based A2P SMS aggregator (esms.com.bd).
 *
 * Endpoint: https://login.esms.com.bd/api/v3/sms/send. Auth = Bearer token in
 * the Authorization header. Send body is form-encoded with recipient /
 * sender_id / type=plain / message; success returns {"status":"success",
 * "data":{...}} and failure returns {"status":"error","message":"..."}.
 *
 * No documented balance endpoint — getCredit() inherits AbstractProvider's
 * null default. testConnection() probes GET /api/v3/sms/ for a cheap auth check
 * (200 = ok, 401/403 = invalid token).
 */
class EsmsbdProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_SEND = 'https://login.esms.com.bd/api/v3/sms/send';
    private const API_LIST = 'https://login.esms.com.bd/api/v3/sms/';

    public function getId(): string
    {
        return 'esmsbd';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_token' => [
                    'type'        => 'secret',
                    'label'       => __('API Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Bearer token from your eSMS Bangladesh customer panel at login.esms.com.bd.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Pre-approved alphanumeric sender ID (max 11 characters) registered with Bangladesh carriers, or a phone number with country code.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $token = $this->getSharedConfig('api_token');
        if (!$token) {
            return DeliveryResult::failed(__('eSMS Bangladesh API Token not configured', 'wp-sms'));
        }

        $senderId = $this->getChannelConfig('sms', 'sender_id');
        if (!$senderId) {
            return DeliveryResult::failed(__('eSMS Bangladesh Sender ID not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_SEND, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
            'body' => [
                'recipient' => $message->getRecipient(),
                'sender_id' => (string) $senderId,
                'type'      => 'plain',
                'message'   => $message->getBody(),
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid eSMS Bangladesh API Token', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(sprintf(__('Invalid response from eSMS Bangladesh (HTTP %d)', 'wp-sms'), $result['code']));
        }

        if (($data['status'] ?? '') === 'success') {
            $uid = $data['data']['uid'] ?? $data['data']['id'] ?? null;
            return DeliveryResult::queued($uid !== null ? (string) $uid : null);
        }

        $error = (string) ($data['message'] ?? sprintf(__('eSMS Bangladesh request failed (HTTP %d)', 'wp-sms'), $result['code']));
        return DeliveryResult::failed($error);
    }

    public function testConnection(): TestConnectionResult
    {
        $token = $this->getSharedConfig('api_token');
        if (!$token) {
            return TestConnectionResult::error(__('API Token is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_LIST, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ]);

        if (!($result instanceof DeliveryResult) && ($result['code'] === 401 || $result['code'] === 403)) {
            return TestConnectionResult::error(__('Invalid eSMS Bangladesh API Token', 'wp-sms'));
        }

        $data = $this->validateTestResponse($result, 'eSMS Bangladesh');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        return TestConnectionResult::ok(__('Connected to eSMS Bangladesh', 'wp-sms'));
    }
}

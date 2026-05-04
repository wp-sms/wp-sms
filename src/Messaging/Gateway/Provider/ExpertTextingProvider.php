<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * ExpertTexting — global SMS aggregator (200+ destinations).
 *
 * Auth: username + api_key + api_secret, all three passed as query parameters
 * on every call (provider's REST surface is GET-only).
 *
 * Send:    GET https://www.experttexting.com/ExptRestApi/sms/json/Message/Send
 * Balance: GET https://www.experttexting.com/ExptRestApi/sms/json/Account/Balance
 *
 * Response envelope: { "Status": 0, "Response": { ... } } on success;
 * { "Status": <non-zero>, "ErrorMessage": "..." } on failure.
 */
class ExpertTextingProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = true;

    private const API_BASE = 'https://www.experttexting.com/ExptRestApi/sms/json';

    public function getId(): string
    {
        return 'experttexting';
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
                    'description' => __('Your ExpertTexting account username.', 'wp-sms'),
                ],
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generated under Account Settings → API in your ExpertTexting customer portal.', 'wp-sms'),
                ],
                'api_secret' => [
                    'type'        => 'secret',
                    'label'       => __('API Secret', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Paired with the API Key under Account Settings → API.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Alphanumeric sender ID or phone number in international format.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $auth = $this->authParams();
        $from = $this->getChannelConfig('sms', 'from');

        if ($auth === null || !$from) {
            return DeliveryResult::failed(__('ExpertTexting credentials not configured', 'wp-sms'));
        }

        $body = $message->getBody();
        $type = preg_match('/[^\x00-\x7F]/', $body) ? 'unicode' : 'text';

        $url = add_query_arg(array_merge($auth, [
            'from' => $from,
            'to'   => ltrim($message->getRecipient(), '+'),
            'text' => $body,
            'type' => $type,
        ]), self::API_BASE . '/Message/Send');

        $result = $this->httpGet($url);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid ExpertTexting credentials', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);
        $status = is_array($data) ? ($data['Status'] ?? null) : null;

        if ($result['code'] >= 200 && $result['code'] < 300 && $status === 0) {
            return DeliveryResult::sent(providerId: $this->extractMessageId($data['Response'] ?? null));
        }

        $errorMessage = is_array($data) ? ($data['ErrorMessage'] ?? null) : null;

        return DeliveryResult::failed(
            $errorMessage ?? sprintf(__('ExpertTexting send failed (code %s)', 'wp-sms'), $status ?? $result['code']),
            meta: array_filter(['experttexting_status' => $status], fn($v) => $v !== null),
        );
    }

    public function getCredit(): ?string
    {
        $auth = $this->authParams();
        if ($auth === null) {
            return null;
        }

        $result = $this->httpGet(add_query_arg($auth, self::API_BASE . '/Account/Balance'));

        if ($result instanceof DeliveryResult) {
            return null;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || ($data['Status'] ?? null) !== 0) {
            return null;
        }

        $balance = $data['Response']['Balance'] ?? null;
        return $balance !== null ? (string) $balance : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $auth = $this->authParams();
        if ($auth === null) {
            return TestConnectionResult::error(__('Username, API Key, and API Secret are required', 'wp-sms'));
        }

        $result = $this->httpGet(add_query_arg($auth, self::API_BASE . '/Account/Balance'));

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid ExpertTexting credentials', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'ExpertTexting');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (($data['Status'] ?? null) !== 0) {
            return TestConnectionResult::error(
                $data['ErrorMessage'] ?? __('Invalid ExpertTexting credentials', 'wp-sms')
            );
        }

        $balance = $data['Response']['Balance'] ?? 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s credits', 'wp-sms'), $balance),
            ['balance' => $balance],
        );
    }

    /**
     * @return array{username: string, api_key: string, api_secret: string}|null
     */
    private function authParams(): ?array
    {
        $username   = $this->getSharedConfig('username');
        $apiKey     = $this->getSharedConfig('api_key');
        $apiSecret  = $this->getSharedConfig('api_secret');

        if (!$username || !$apiKey || !$apiSecret) {
            return null;
        }

        return [
            'username'   => $username,
            'api_key'    => $apiKey,
            'api_secret' => $apiSecret,
        ];
    }

    private function extractMessageId(mixed $response): ?string
    {
        if (!is_array($response)) {
            return null;
        }
        foreach (['MessageID', 'MessageId', 'message_id', 'id'] as $key) {
            if (isset($response[$key]) && is_scalar($response[$key])) {
                return (string) $response[$key];
            }
        }
        foreach ($response as $value) {
            if (is_scalar($value)) {
                return (string) $value;
            }
        }
        return null;
    }
}

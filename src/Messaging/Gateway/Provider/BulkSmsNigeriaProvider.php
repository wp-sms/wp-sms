<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

class BulkSmsNigeriaProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const BASE_URL       = 'https://www.bulksmsnigeria.com/api/v2';
    private const DEFAULT_ROUTE  = 'direct-refund';

    public function getId(): string
    {
        return 'bulksmsnigeria';
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
                    'description' => __('Generate from your BulkSMSNigeria dashboard under the developer/API section.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => false,
                        'placeholder' => 'MyBrand',
                        'description' => __('Pre-approved alphanumeric sender ID, max 11 characters. Approval takes 3–7 business days across MTN, Glo, Airtel, and 9mobile.', 'wp-sms'),
                    ],
                    'route' => [
                        'type'        => 'select',
                        'label'       => __('Route', 'wp-sms'),
                        'required'    => false,
                        'default'     => self::DEFAULT_ROUTE,
                        'options'     => [
                            ['value' => 'direct-refund',    'label' => __('Standard (refunds failed sends)', 'wp-sms')],
                            ['value' => 'direct-corporate', 'label' => __('DND-reachable (corporate route)', 'wp-sms')],
                            ['value' => 'otp',              'label' => __('OTP-optimized', 'wp-sms')],
                            ['value' => 'dual-backup',      'label' => __('Dual backup', 'wp-sms')],
                        ],
                        'description' => __('Pick the BulkSMSNigeria delivery route. "DND-reachable" charges more but reaches numbers on the National DND list.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiToken = $this->getSharedConfig('api_token');

        if (!$apiToken) {
            return DeliveryResult::failed(__('BulkSMSNigeria API token not configured', 'wp-sms'));
        }

        $body = [
            'to'   => $this->stripPlus($message->getRecipient()),
            'body' => $message->getBody(),
        ];

        $from = $this->getChannelConfig('sms', 'from');
        if (!empty($from)) {
            $body['from'] = $from;
        }

        $route = $this->getChannelConfig('sms', 'route', self::DEFAULT_ROUTE);
        if (!empty($route) && $route !== self::DEFAULT_ROUTE) {
            $body['gateway'] = $route;
        }

        $result = $this->httpPost(self::BASE_URL . '/sms', [
            'headers' => $this->authHeaders(),
            'body'    => json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid BulkSMSNigeria API token', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(sprintf('HTTP %d', $result['code']));
        }

        if ($this->parseSuccess($data)) {
            $payload = $data['data'] ?? [];

            $providerId = $payload['message_id']
                ?? ($payload['recipients'][0]['message_id'] ?? null);

            $cost = $payload['cost'] ?? $payload['total_cost'] ?? null;

            return DeliveryResult::sent(
                providerId: $providerId !== null ? (string) $providerId : null,
                cost:       $cost !== null ? (float) $cost : null,
            );
        }

        $errorMessage = $data['message']
            ?? $data['error']
            ?? sprintf('HTTP %d', $result['code']);

        $errorCode = $data['error_code'] ?? $data['code'] ?? null;

        return DeliveryResult::failed(
            (string) $errorMessage,
            meta: array_filter(['error_code' => $errorCode]),
        );
    }

    public function getCredit(): ?string
    {
        $apiToken = $this->getSharedConfig('api_token');
        if (!$apiToken) {
            return null;
        }

        $result = $this->httpGet(self::BASE_URL . '/balance', [
            'headers' => $this->authHeaders(),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !$this->parseSuccess($data)) {
            return null;
        }

        return $this->formatBalance($data['data'] ?? []);
    }

    public function testConnection(): TestConnectionResult
    {
        $apiToken = $this->getSharedConfig('api_token');
        if (!$apiToken) {
            return TestConnectionResult::error(__('API Token is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::BASE_URL . '/balance', [
            'headers' => $this->authHeaders(),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid API Token', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'BulkSMSNigeria');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (!$this->parseSuccess($data)) {
            $msg = $data['message'] ?? __('Unexpected response from BulkSMSNigeria', 'wp-sms');
            return TestConnectionResult::error((string) $msg);
        }

        $balance = $this->formatBalance($data['data'] ?? []) ?? 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s', 'wp-sms'), $balance),
            ['balance' => $balance],
        );
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . (string) $this->getSharedConfig('api_token'),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    private function stripPlus(string $recipient): string
    {
        return ltrim($recipient, '+');
    }

    private function parseSuccess(array $data): bool
    {
        return ($data['success'] ?? null) === true
            || ($data['status'] ?? null) === 'success';
    }

    private function formatBalance(array $payload): ?string
    {
        if (!array_key_exists('balance', $payload)) {
            return null;
        }
        $balance  = $payload['balance'];
        $currency = $payload['currency'] ?? 'NGN';
        return trim(sprintf('%s %s', $currency, $balance));
    }
}

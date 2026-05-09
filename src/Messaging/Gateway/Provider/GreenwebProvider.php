<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

class GreenwebProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_URL    = 'https://api.greenweb.com.bd/api.php?json';
    private const BALANCE_URL = 'https://api.greenweb.com.bd/g_api.php';

    public function getId(): string
    {
        return 'greenweb';
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
                    'description' => __('Generate from your Greenweb SMS panel under API Token.', 'wp-sms'),
                ],
            ],
            'channels' => ['sms' => []],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiToken = $this->getSharedConfig('api_token');

        if (!$apiToken) {
            return DeliveryResult::failed(__('Greenweb API token not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::SEND_URL, [
            'headers' => ['Accept' => 'application/json'],
            'body'    => [
                'token'   => $apiToken,
                'to'      => $message->getRecipient(),
                'message' => $message->getBody(),
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Greenweb API token', 'wp-sms'));
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(sprintf('HTTP %d', $result['code']));
        }

        $data = json_decode($result['body'], true);
        $entry = is_array($data) && isset($data[0]) && is_array($data[0]) ? $data[0] : null;

        if ($entry === null) {
            return DeliveryResult::failed(__('Invalid response from Greenweb', 'wp-sms'));
        }

        $status = strtoupper((string) ($entry['status'] ?? ''));
        if ($status === 'SENT') {
            return DeliveryResult::sent(providerId: null);
        }

        $reason = (string) ($entry['statusmsg'] ?? __('Greenweb returned an unknown error', 'wp-sms'));
        return DeliveryResult::failed($reason, meta: array_filter(['status' => $entry['status'] ?? null]));
    }

    public function getCredit(): ?string
    {
        $apiToken = $this->getSharedConfig('api_token');
        if (!$apiToken) {
            return null;
        }

        $result = $this->httpGet($this->balanceUrl($apiToken));

        if ($result instanceof DeliveryResult) {
            return null;
        }
        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        return $this->parseBalance((string) $result['body']);
    }

    public function testConnection(): TestConnectionResult
    {
        $apiToken = $this->getSharedConfig('api_token');
        if (!$apiToken) {
            return TestConnectionResult::error(__('API Token is required', 'wp-sms'));
        }

        $result = $this->httpGet($this->balanceUrl($apiToken));

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid API Token', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Greenweb');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = $this->parseBalance(json_encode($data));
        if ($balance === null) {
            return TestConnectionResult::error(__('Greenweb did not return a balance', 'wp-sms'));
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s', 'wp-sms'), $balance),
            ['balance' => $balance],
        );
    }

    private function balanceUrl(string $apiToken): string
    {
        return self::BALANCE_URL . '?' . http_build_query(['token' => $apiToken]) . '&balance&json';
    }

    /**
     * Greenweb's balance endpoint returns a small JSON shape — observed forms
     * include `{balance: "12.34"}` and `[{balance: "12.34"}]`. Pull the first
     * numeric value we recognise out of either shape.
     */
    private function parseBalance(string $body): ?string
    {
        $body = trim($body);
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return is_numeric($body) ? $body : null;
        }

        $candidates = isset($decoded[0]) && is_array($decoded[0]) ? [$decoded[0]] : [$decoded];
        foreach ($candidates as $row) {
            foreach (['balance', 'Balance', 'amount', 'credit'] as $key) {
                if (isset($row[$key]) && (is_numeric($row[$key]) || is_string($row[$key]))) {
                    return (string) $row[$key];
                }
            }
        }

        return null;
    }
}

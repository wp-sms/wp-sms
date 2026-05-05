<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * SMS.dk — Danish SMS gateway by Compaya A/S (sister product of CPSMS.dk).
 *
 * Auth: Bearer token from https://login.sms.dk/account/settings/edit#APIKey.
 * Send: POST /v1/sms/send JSON {receiver, senderName, message, encoding}.
 * Credit: GET /v1/user/getcreditvalue → {result: <number>}.
 * Sender names: GET /v1/sendername/list → {result: [{senderName, …}, …]}.
 *
 * No documented DLR field names and no inbound MO endpoint, so neither
 * SupportsStatusCallback nor SupportsInboundMessage is implemented — sends
 * resolve to `queued` (acceptance) rather than `sent` (delivered).
 */
class ProsmsdkProvider extends AbstractProvider implements SupportsDynamicOptions
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.sms.dk';

    public function getId(): string
    {
        return 'prosmsdk';
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
                    'description' => __('Generate at https://login.sms.dk/account/settings/edit#APIKey. Treat as a credit-card-equivalent secret — anyone with the key can drain your SMS.dk balance.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_name' => [
                        'type'        => 'string',
                        'label'       => __('Sender Name', 'wp-sms'),
                        'required'    => true,
                        'dynamic'     => true,
                        'description' => __('3–15 chars; alphanumeric capped at 11. Must be pre-validated in the sms.dk web interface before it can be selected here.', 'wp-sms'),
                        'placeholder' => 'CompanyName',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('SMS.dk API key not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender_name', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('SMS.dk sender name not configured', 'wp-sms'));
        }

        $receiver = (int) preg_replace('/\D+/', '', $message->getRecipient());

        $result = $this->httpPost(self::API_BASE . '/v1/sms/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body' => wp_json_encode([
                'receiver'   => $receiver,
                'senderName' => $sender,
                'message'    => $message->getBody(),
                'encoding'   => 'UTF-8',
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $code = (int) $result['code'];
        $data = json_decode($result['body'], true);

        if (($code === 200 || $code === 207) && is_array($data)) {
            $reportAccepted = $data['result']['report']['accepted'] ?? [];
            if (!empty($reportAccepted)) {
                $batchId = isset($data['result']['batchId']) ? (string) $data['result']['batchId'] : null;
                $totalCreditSum = $data['result']['totalCreditSum'] ?? null;
                return DeliveryResult::queued(
                    $batchId,
                    meta: $totalCreditSum !== null ? ['prosmsdk_credit_cost' => (string) $totalCreditSum] : [],
                );
            }
        }

        // All-rejected on 200/207, or any 4xx/5xx — surface sms.dk's own message.
        $errorMsg = is_array($data) && isset($data['message'])
            ? (string) $data['message']
            : sprintf('HTTP %d', $code);
        $messageCode = is_array($data) && isset($data['messageCode'])
            ? (string) $data['messageCode']
            : null;

        return DeliveryResult::failed($errorMsg, array_filter([
            'prosmsdk_code' => $messageCode,
            'prosmsdk_http' => $code ? (string) $code : null,
        ]));
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/v1/user/getcreditvalue', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept'        => 'application/json',
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        if ((int) $result['code'] < 200 || (int) $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        $credit = $data['result'] ?? null;
        return is_numeric($credit) ? (string) $credit : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/v1/user/getcreditvalue', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept'        => 'application/json',
            ],
        ]);

        if (!$result instanceof DeliveryResult) {
            $code = (int) $result['code'];
            if ($code === 401 || $code === 403) {
                return TestConnectionResult::error(__('Invalid SMS.dk API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'SMS.dk');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $credit = isset($data['result']) && is_numeric($data['result']) ? (string) $data['result'] : null;
        if ($credit === null) {
            return TestConnectionResult::error(__('Unexpected response from SMS.dk', 'wp-sms'));
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'sender_name' || $section !== 'sms') {
            return [];
        }

        return $this->withConfig($config, function () {
            $apiKey = $this->getSharedConfig('api_key');
            $data = $this->fetchJsonOrFail(self::API_BASE . '/v1/sendername/list', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept'        => 'application/json',
                ],
            ]);

            $options = [];
            foreach ($data['result'] ?? [] as $item) {
                if (!is_array($item) || !isset($item['senderName'])) {
                    continue;
                }
                $name = (string) $item['senderName'];
                $options[] = ['value' => $name, 'label' => $name];
            }
            return $options;
        });
    }
}

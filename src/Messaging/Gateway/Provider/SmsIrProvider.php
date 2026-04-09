<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

class SmsIrProvider extends AbstractProvider
{
    private const API_BASE = 'https://api.sms.ir/v1';

    public function getId(): string
    {
        return 'smsir';
    }

    public function getName(): string
    {
        return 'SMS.ir';
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
                    'description' => __('Your API key from the SMS.ir panel under Developer API section', 'wp-sms'),
                    'placeholder' => 'Your SMS.ir API key',
                ],
            ],
            'channels' => [
                'sms' => [
                    'line_number' => [
                        'type'        => 'string',
                        'label'       => __('Line Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Your dedicated sender line number from the SMS.ir panel', 'wp-sms'),
                        'placeholder' => '30001234567890',
                    ],
                ],
            ],
        ];
    }

    public function getMetadata(): array
    {
        return [
            'description' => __('Iranian SMS service provider', 'wp-sms'),
            'website'     => 'https://sms.ir',
            'icon'        => '',
            'regions'     => ['IR'],
            'setup_url'   => 'https://app.sms.ir/',
            'setup_notes' => [
                __('Find your API Key in the SMS.ir panel under the Developer API section.', 'wp-sms'),
                __('Your line number is listed in the SMS.ir panel under Lines.', 'wp-sms'),
            ],
        ];
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'test_connection' => true,
        ]);
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        $lineNumber = $this->getChannelConfig('sms', 'line_number');

        if (!$apiKey || !$lineNumber) {
            return DeliveryResult::failed(__('SMS.ir credentials not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/send/bulk', [
            'headers' => [
                'X-API-KEY'    => $apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode([
                'lineNumber'  => $lineNumber,
                'messageText' => $message->getBody(),
                'mobiles'     => [$message->getRecipient()],
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if (($data['status'] ?? 0) === 1) {
            $messageId = $data['data']['messageIds'][0] ?? null;
            return DeliveryResult::sent(
                providerId: $messageId !== null ? (string) $messageId : null,
                cost: isset($data['data']['cost']) ? (float) $data['data']['cost'] : null,
            );
        }

        return DeliveryResult::failed($data['message'] ?? __('SMS.ir send failed', 'wp-sms'));
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/credit', [
            'headers' => [
                'X-API-KEY' => $apiKey,
                'Accept'    => 'application/json',
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);

        if (($data['status'] ?? 0) === 1) {
            return (string) ($data['data'] ?? '');
        }

        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');

        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/credit', [
            'headers' => [
                'X-API-KEY' => $apiKey,
                'Accept'    => 'application/json',
            ],
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'SMS.ir');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (($data['status'] ?? 0) !== 1) {
            return TestConnectionResult::error($data['message'] ?? __('Unknown error', 'wp-sms'));
        }

        $credit = (string) ($data['data'] ?? 'N/A');

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }
}

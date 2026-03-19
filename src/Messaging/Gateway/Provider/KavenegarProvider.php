<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Contracts\TestConnectionResult;

defined('ABSPATH') || exit;

class KavenegarProvider extends AbstractProvider
{
    private const API_BASE = 'https://api.kavenegar.com/v1';

    public function getId(): string
    {
        return 'kavenegar';
    }

    public function getName(): string
    {
        return 'Kavenegar';
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
                    'description' => __('Found in your Kavenegar Panel under Settings > API Key', 'wp-sms'),
                    'placeholder' => 'Your Kavenegar API key',
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Your dedicated line number from the Kavenegar Panel > SMS > Lines', 'wp-sms'),
                        'placeholder' => '10001234567890',
                    ],
                ],
            ],
        ];
    }

    public function getMetadata(): array
    {
        return [
            'description' => __('Iranian SMS and messaging service provider', 'wp-sms'),
            'website'     => 'https://kavenegar.com',
            'icon'        => '',
            'regions'     => ['IR'],
            'setup_url'   => 'https://panel.kavenegar.com/',
            'setup_notes' => [
                __('Find your API Key in the Kavenegar Panel under Settings > API Key.', 'wp-sms'),
                __('Your sender number (line) is listed under SMS > Lines in the panel.', 'wp-sms'),
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        $sender = $this->getChannelConfig('sms', 'sender');

        if (!$apiKey || !$sender) {
            return DeliveryResult::failed(__('Kavenegar credentials not configured', 'wp-sms'));
        }

        $url = self::API_BASE . "/{$apiKey}/sms/send.json";

        $result = $this->httpPost($url, [
            'body' => [
                'receptor' => $message->getRecipient(),
                'sender'   => $sender,
                'message'  => $message->getBody(),
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if (($data['return']['status'] ?? 0) === 200) {
            $entry = $data['entries'][0] ?? [];
            return DeliveryResult::sent(
                providerId: (string) ($entry['messageid'] ?? ''),
                cost: isset($entry['cost']) ? (float) $entry['cost'] : null,
            );
        }

        return DeliveryResult::failed($data['return']['message'] ?? __('Kavenegar send failed', 'wp-sms'));
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $url = self::API_BASE . "/{$apiKey}/account/info.json";
        $result = $this->httpGet($url);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        return (string) ($data['entries']['remaincredit'] ?? '');
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'test_connection' => true,
        ]);
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');

        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $url = self::API_BASE . "/{$apiKey}/account/info.json";
        $result = $this->httpGet($url);

        $data = $this->validateTestResponse($result, 'Kavenegar');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $status = $data['return']['status'] ?? 0;
        if ($status !== 200) {
            $message = $data['return']['message'] ?? __('Unknown error', 'wp-sms');
            return TestConnectionResult::error($message);
        }

        $credit = (string) ($data['entries']['remaincredit'] ?? 'N/A');

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }
}

<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\AbstractProvider;

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
                    'type'     => 'secret',
                    'label'    => __('API Key', 'wp-sms'),
                    'required' => true,
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'     => 'string',
                        'label'    => __('Sender Number', 'wp-sms'),
                        'required' => true,
                        'description' => __('Your Kavenegar line number', 'wp-sms'),
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
}

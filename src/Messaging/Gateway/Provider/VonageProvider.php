<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

class VonageProvider extends AbstractProvider
{
    private const API_URL = 'https://rest.nexmo.com/sms/json';
    private const BALANCE_URL = 'https://rest.nexmo.com/account/get-balance';

    public function getId(): string
    {
        return 'vonage';
    }

    public function getName(): string
    {
        return 'Vonage (Nexmo)';
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
                    'type'     => 'string',
                    'label'    => __('API Key', 'wp-sms'),
                    'required' => true,
                ],
                'api_secret' => [
                    'type'     => 'secret',
                    'label'    => __('API Secret', 'wp-sms'),
                    'required' => true,
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'     => 'string',
                        'label'    => __('Sender ID', 'wp-sms'),
                        'required' => true,
                        'description' => __('Phone number or alphanumeric sender ID', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    public function getMetadata(): array
    {
        return [
            'description' => __('Global cloud communications provider (formerly Nexmo)', 'wp-sms'),
            'website'     => 'https://www.vonage.com',
            'icon'        => '',
            'regions'     => ['global'],
        ];
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'bulk_send'        => true,
            'delivery_receipt' => true,
            'incoming'         => true,
        ]);
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        $apiSecret = $this->getSharedConfig('api_secret');
        $from = $this->getChannelConfig('sms', 'from');

        if (!$apiKey || !$apiSecret || !$from) {
            return DeliveryResult::failed(__('Vonage credentials not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_URL, [
            'body' => wp_json_encode([
                'api_key'    => $apiKey,
                'api_secret' => $apiSecret,
                'from'       => $from,
                'to'         => $message->getRecipient(),
                'text'       => $message->getBody(),
                'type'       => 'unicode',
            ]),
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        $msg = $data['messages'][0] ?? [];

        if (($msg['status'] ?? '1') === '0') {
            return DeliveryResult::sent(
                providerId: $msg['message-id'] ?? null,
                cost: isset($msg['message-price']) ? (float) $msg['message-price'] : null,
            );
        }

        return DeliveryResult::failed($msg['error-text'] ?? __('Vonage send failed', 'wp-sms'));
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        $apiSecret = $this->getSharedConfig('api_secret');

        if (!$apiKey || !$apiSecret) {
            return null;
        }

        $result = $this->httpGet(self::BALANCE_URL . "?api_key={$apiKey}&api_secret={$apiSecret}");

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        return isset($data['value']) ? number_format((float) $data['value'], 2) : null;
    }
}

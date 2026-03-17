<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

class TwilioProvider extends AbstractProvider
{
    private const API_BASE = 'https://api.twilio.com/2010-04-01';

    public function getId(): string
    {
        return 'twilio';
    }

    public function getName(): string
    {
        return 'Twilio';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'account_sid' => [
                    'type'     => 'string',
                    'label'    => __('Account SID', 'wp-sms'),
                    'required' => true,
                ],
                'auth_token' => [
                    'type'     => 'secret',
                    'label'    => __('Auth Token', 'wp-sms'),
                    'required' => true,
                ],
            ],
            'channels' => [
                'sms' => [
                    'from_number' => [
                        'type'     => 'string',
                        'label'    => __('From Number', 'wp-sms'),
                        'required' => true,
                        'description' => __('Your Twilio phone number in E.164 format', 'wp-sms'),
                    ],
                ],
                'whatsapp' => [
                    'from_number' => [
                        'type'     => 'string',
                        'label'    => __('WhatsApp Number', 'wp-sms'),
                        'required' => true,
                        'description' => __('Your Twilio WhatsApp-enabled number in E.164 format', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    public function getMetadata(): array
    {
        return [
            'description' => __('Cloud communications platform for SMS, WhatsApp, and more', 'wp-sms'),
            'website'     => 'https://www.twilio.com',
            'icon'        => '',
            'regions'     => ['global'],
        ];
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'bulk_send'        => true,
            'mms'              => true,
            'delivery_receipt' => true,
            'incoming'         => true,
            'scheduled_send'   => true,
        ]);
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $accountSid = $this->getSharedConfig('account_sid');
        $authToken = $this->getSharedConfig('auth_token');
        $channel = $message->getChannel();
        $from = $this->getChannelConfig($channel, 'from_number');

        if (!$accountSid || !$authToken || !$from) {
            return DeliveryResult::failed(__('Twilio credentials not configured', 'wp-sms'));
        }

        $to = $message->getRecipient();
        // WhatsApp requires the whatsapp: prefix
        if ($channel === 'whatsapp') {
            $from = 'whatsapp:' . $from;
            $to = 'whatsapp:' . $to;
        }

        $url = self::API_BASE . "/Accounts/{$accountSid}/Messages.json";

        $result = $this->httpPost($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("{$accountSid}:{$authToken}"),
            ],
            'body' => [
                'From' => $from,
                'To'   => $to,
                'Body' => $message->getBody(),
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] >= 200 && $result['code'] < 300) {
            return DeliveryResult::sent(
                providerId: $data['sid'] ?? null,
                cost: isset($data['price']) ? abs((float) $data['price']) : null,
            );
        }

        return DeliveryResult::failed($data['message'] ?? "HTTP {$result['code']}");
    }

    public function getCredit(): ?string
    {
        $accountSid = $this->getSharedConfig('account_sid');
        $authToken = $this->getSharedConfig('auth_token');

        if (!$accountSid || !$authToken) {
            return null;
        }

        $url = self::API_BASE . "/Accounts/{$accountSid}/Balance.json";

        $result = $this->httpGet($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("{$accountSid}:{$authToken}"),
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        return $data['balance'] ?? null;
    }
}

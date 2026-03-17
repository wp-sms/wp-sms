<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

class NetGsmProvider extends AbstractProvider
{
    private const API_URL = 'https://api.netgsm.com.tr/sms/send/get';
    private const CREDIT_URL = 'https://api.netgsm.com.tr/balance/list/get';

    public function getId(): string
    {
        return 'netgsm';
    }

    public function getName(): string
    {
        return 'NetGSM';
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
                    'type'     => 'string',
                    'label'    => __('Username', 'wp-sms'),
                    'required' => true,
                ],
                'password' => [
                    'type'     => 'secret',
                    'label'    => __('Password', 'wp-sms'),
                    'required' => true,
                ],
            ],
            'channels' => [
                'sms' => [
                    'msgheader' => [
                        'type'        => 'string',
                        'label'       => __('Message Header', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Approved sender name in your NetGSM panel', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    public function getMetadata(): array
    {
        return [
            'description' => __('Turkish SMS and communication services provider', 'wp-sms'),
            'website'     => 'https://www.netgsm.com.tr',
            'icon'        => '',
            'regions'     => ['TR'],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');
        $msgheader = $this->getChannelConfig('sms', 'msgheader');

        if (!$username || !$password || !$msgheader) {
            return DeliveryResult::failed(__('NetGSM credentials not configured', 'wp-sms'));
        }

        $params = http_build_query([
            'usercode'  => $username,
            'password'  => $password,
            'gsmno'     => $message->getRecipient(),
            'message'   => $message->getBody(),
            'msgheader' => $msgheader,
            'dil'       => 'TR',
        ]);

        $result = $this->httpGet(self::API_URL . '?' . $params);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $body = trim($result['body']);

        // NetGSM returns a numeric code; codes starting with "00" or "01" are success
        if (str_starts_with($body, '00') || str_starts_with($body, '01')) {
            $parts = explode(' ', $body, 2);
            return DeliveryResult::sent(providerId: $parts[1] ?? null);
        }

        $errors = [
            '20' => 'Post error',
            '30' => 'Invalid username/password or API access disabled',
            '40' => 'Sender ID not approved',
            '50' => 'Recipient number missing',
            '51' => 'Message body too long',
            '70' => 'Invalid parameters',
            '80' => 'Query limit exceeded',
            '85' => 'Duplicate message within 10 minutes',
        ];

        return DeliveryResult::failed($errors[$body] ?? "NetGSM error: {$body}");
    }

    public function getCredit(): ?string
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return null;
        }

        $params = http_build_query([
            'usercode' => $username,
            'password' => $password,
            'stession' => 'ALL',
        ]);

        $result = $this->httpGet(self::CREDIT_URL . '?' . $params);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $body = trim($result['body']);
        // Response format: credit|...
        $parts = explode('|', $body);
        return $parts[0] ?? null;
    }
}

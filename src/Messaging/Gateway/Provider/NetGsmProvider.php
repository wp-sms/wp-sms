<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Contracts\TestConnectionResult;

defined('ABSPATH') || exit;

class NetGsmProvider extends AbstractProvider
{
    private const API_URL = 'https://api.netgsm.com.tr/sms/send/get';
    private const CREDIT_URL = 'https://api.netgsm.com.tr/balance/list/get';

    public function getId(): string
    {
        return 'netgsm';
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
                    'description' => __('Your NetGSM account username', 'wp-sms'),
                    'placeholder' => 'Your NetGSM username',
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your NetGSM account password', 'wp-sms'),
                    'placeholder' => 'Your NetGSM password',
                ],
            ],
            'channels' => [
                'sms' => [
                    'msgheader' => [
                        'type'        => 'string',
                        'label'       => __('Message Header', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Your approved sender name from the NetGSM panel. Must be pre-approved.', 'wp-sms'),
                        'placeholder' => 'MYCOMPANY',
                    ],
                ],
            ],
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

    public function testConnection(): TestConnectionResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return TestConnectionResult::error(__('Username and Password are required', 'wp-sms'));
        }

        $params = http_build_query([
            'usercode' => $username,
            'password' => $password,
            'stession' => 'ALL',
        ]);

        $result = $this->httpGet(self::CREDIT_URL . '?' . $params);

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(__('Could not reach the NetGSM API. Check your server\'s internet connection.', 'wp-sms'));
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from NetGSM (HTTP %d)', 'wp-sms'), $result['code'])
            );
        }

        $body = trim($result['body']);

        $errors = [
            '30' => __('Invalid username or password', 'wp-sms'),
            '40' => __('API access disabled for this account', 'wp-sms'),
            '70' => __('Invalid request parameters', 'wp-sms'),
        ];

        if (isset($errors[$body])) {
            return TestConnectionResult::error($errors[$body]);
        }

        // A short numeric-only response that isn't a known balance format is an error code
        if (preg_match('/^\d{2}$/', $body)) {
            return TestConnectionResult::error(sprintf(__('NetGSM error: %s', 'wp-sms'), $body));
        }

        // Response format: credit|...
        $parts = explode('|', $body);
        $credit = $parts[0] ?? 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }
}

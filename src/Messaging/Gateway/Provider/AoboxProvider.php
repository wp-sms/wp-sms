<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

class AoboxProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE        = 'https://aobox.it/app';
    private const DEFAULT_SENDER  = 'Aobox';
    private const DEFAULT_ROUTE   = '3';

    public function getId(): string
    {
        return 'aobox';
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
                    'description' => __('Your Aobox username from the customer area at aobox.it/app/.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Aobox account password.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Optional sender name, max 11 characters. Defaults to "Aobox" when blank. Some routes require a fixed sender.', 'wp-sms'),
                        'placeholder' => self::DEFAULT_SENDER,
                    ],
                    'route' => [
                        'type'        => 'string',
                        'label'       => __('Route', 'wp-sms'),
                        'required'    => false,
                        'default'     => self::DEFAULT_ROUTE,
                        'description' => __('Route number provided by your Aobox sales account; different routes carry different quality/pricing.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return DeliveryResult::failed(__('Aobox credentials not configured', 'wp-sms'));
        }

        $sender = $this->getChannelConfig('sms', 'from') ?: self::DEFAULT_SENDER;
        $route  = $this->getChannelConfig('sms', 'route') ?: self::DEFAULT_ROUTE;

        $params = [
            'version'  => '3',
            'username' => $username,
            'password' => $password,
            'route'    => $route,
            'sender'   => $sender,
            'rcpt'     => $message->getRecipient(),
            'text'     => $message->getBody(),
        ];

        $result = $this->httpPost(self::API_BASE . '/gateway.php', ['body' => $params]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        return $this->parseSendResponse($result['body']);
    }

    public function getCredit(): ?string
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return null;
        }

        $result = $this->httpPost(self::API_BASE . '/getcred3.php', [
            'body' => ['username' => $username, 'password' => $password],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $body = trim((string) $result['body']);
        if ($body === '' || !is_numeric($body)) {
            return null;
        }

        return $body;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return TestConnectionResult::error(__('Username and Password are required', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/getcred3.php', [
            'body' => ['username' => $username, 'password' => $password],
        ]);

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the Aobox API. Check your server\'s internet connection.', 'wp-sms')
            );
        }

        $body = trim((string) $result['body']);

        if (stripos($body, 'UNAUTHORIZED') !== false) {
            return TestConnectionResult::error(__('Invalid Aobox Username or Password', 'wp-sms'));
        }

        if (is_numeric($body)) {
            return TestConnectionResult::ok(
                sprintf(__('Connected — Balance: %s', 'wp-sms'), $body),
                ['balance' => $body],
            );
        }

        return TestConnectionResult::error(
            sprintf(__('Unexpected response from Aobox: %s', 'wp-sms'), $body !== '' ? $body : 'empty')
        );
    }

    private function parseSendResponse(string $body): DeliveryResult
    {
        $trimmed = trim($body);
        $status  = null;
        $message = '';
        $cost    = null;

        foreach (preg_split('/\r?\n/', $trimmed) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^status\s*=\s*(-?\d+)\s*;?\s*(.*)$/i', $line, $m)) {
                $status  = $m[1];
                $message = $m[2];
            } elseif (preg_match('/^cost\s*=\s*(.*)$/i', $line, $m)) {
                $cost = trim($m[1]);
            }
        }

        if ($status === '0') {
            return DeliveryResult::sent(
                providerId: substr(md5($trimmed), 0, 16),
                cost: ($cost !== null && $cost !== '' && is_numeric($cost)) ? (float) $cost : null,
            );
        }

        if ($status !== null) {
            return DeliveryResult::failed(
                $message !== '' ? $message : sprintf(__('Aobox rejected the request (status %s)', 'wp-sms'), $status),
                meta: ['aobox_status' => $status],
            );
        }

        if (stripos($trimmed, 'error') !== false) {
            return DeliveryResult::failed($trimmed !== '' ? $trimmed : __('Aobox returned an error', 'wp-sms'));
        }

        return DeliveryResult::failed(
            sprintf(__('Unexpected Aobox response: %s', 'wp-sms'), $trimmed !== '' ? $trimmed : 'empty')
        );
    }
}

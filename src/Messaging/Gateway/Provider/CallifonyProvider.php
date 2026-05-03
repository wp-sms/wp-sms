<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Callifony — Indian-headquartered SMS gateway with UAE-hosted backend.
 *
 * v7 used the same hosts; the developer page at callifony.com/developer
 * references sms.callifony.com which is dead per probe 2026-05-03 —
 * globalsms.ae is the live backend.
 *
 * Auth: query-string username/password on every request.
 * Send: POST push.globalsms.ae/HTTP/api/Client/SendSMS — JSON body, JSON
 *       response with `ErrorCode` (0 = success) and optional message id.
 * Balance: GET access.globalsms.ae/OnlineApi/api/Billing — JSON `Balance`.
 */
final class CallifonyProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_URL    = 'https://push.globalsms.ae/HTTP/api/Client/SendSMS';
    private const BALANCE_URL = 'https://access.globalsms.ae/OnlineApi/api/Billing';

    /** @var array<int, string> Error code → user-facing message. Verbatim port from v7. */
    private const ERROR_MESSAGES = [
        -1  => 'No Text Message specified',
        -2  => 'No Source',
        -3  => 'No Destination',
        -4  => 'Invalid Destination',
        -5  => 'Invalid Credentials',
        -6  => 'No Credit',
        -7  => 'Invalid Data Coding',
        -8  => 'IP Not Whitelisted',
        -10 => 'Unknown Error',
        -11 => 'Invalid Instance Connection',
    ];

    public function getId(): string
    {
        return 'callifony';
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
                    'label'       => __('API Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Callifony panel username.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Callifony panel password.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Sender ID registered and activated in your Callifony panel.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $from     = (string) $this->getChannelConfig('sms', 'from', '');

        if ($username === '' || $password === '' || $from === '') {
            return DeliveryResult::failed(__('Callifony credentials not configured', 'wp-sms'));
        }

        $body = $message->getBody();

        $payload = [
            'source'      => $from,
            'destination' => ltrim($message->getRecipient(), '+'),
            'text'        => $body,
            'dataCoding'  => $this->isUnicode($body) ? 8 : 1,
        ];

        $url = self::SEND_URL
            . '?username=' . rawurlencode($username)
            . '&password=' . rawurlencode($password);

        $result = $this->httpPost($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($payload),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $response = json_decode($result['body']);
        $code     = isset($response->ErrorCode) ? (int) $response->ErrorCode : null;

        if ($code === 0) {
            return DeliveryResult::sent(providerId: $response->MessageId ?? null);
        }

        $error = $code !== null
            ? (self::ERROR_MESSAGES[$code] ?? sprintf(__('Unknown error, code %d', 'wp-sms'), $code))
            : __('Invalid response from Callifony', 'wp-sms');

        return DeliveryResult::failed($error, meta: $code !== null ? ['callifony_code' => $code] : []);
    }

    public function getCredit(): ?string
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');
        if (!$username || !$password) {
            return null;
        }

        $result = $this->httpGet(add_query_arg(
            ['username' => $username, 'password' => $password, 'isEnterprise' => 'false'],
            self::BALANCE_URL,
        ));

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $response = json_decode($result['body']);
        if (!is_object($response) || !isset($response->Balance)) {
            return null;
        }

        return (string) $response->Balance;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');
        if (!$username || !$password) {
            return TestConnectionResult::error(__('Username and password are required', 'wp-sms'));
        }

        $result = $this->httpGet(add_query_arg(
            ['username' => $username, 'password' => $password, 'isEnterprise' => 'false'],
            self::BALANCE_URL,
        ));

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the Callifony API. Check your server\'s internet connection.', 'wp-sms')
            );
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from Callifony (HTTP %d)', 'wp-sms'), $result['code'])
            );
        }

        $response = json_decode($result['body']);

        if (is_object($response) && isset($response->Balance)) {
            $balance = (string) $response->Balance;
            return TestConnectionResult::ok(
                sprintf(__('Connected — Balance: %s', 'wp-sms'), $balance),
                ['balance' => $balance],
            );
        }

        if (is_object($response) && isset($response->ErrorCode)) {
            $code = (int) $response->ErrorCode;
            return TestConnectionResult::error(
                self::ERROR_MESSAGES[$code] ?? sprintf(__('Callifony rejected the request (code %d)', 'wp-sms'), $code)
            );
        }

        return TestConnectionResult::error(__('Invalid response from Callifony', 'wp-sms'));
    }

    private function isUnicode(string $body): bool
    {
        if ($body === '') {
            return false;
        }
        return mb_detect_encoding($body, 'ASCII', true) === false;
    }
}

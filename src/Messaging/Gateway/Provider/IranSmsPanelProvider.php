<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Iran SMS Panel (2972.ir) — Iranian SMS panel with mixed transports.
 *
 * Endpoints:
 *   Send:    POST http://www.2972.ir/api
 *            { username, password, number, recipient, port, message, flash }
 *   Credit:  SOAP http://www.2972.ir/wsdl?XML
 *            Authentication(username, password) -> "1" on success
 *            GetCredit() -> numeric credit
 *
 * The v7 send path treats an empty/falsy response body as success and any
 * non-empty body as the error message; this class preserves that semantic.
 *
 * Out of scope: templates/patterns (not exposed), MMS, status webhooks,
 * inbound webhooks. Credit lookup requires the PHP soap extension.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class IranSmsPanelProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_URL = 'http://www.2972.ir/api';
    private const WSDL     = 'http://www.2972.ir/wsdl?XML';

    public function getId(): string
    {
        return 'iransmspanel';
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
                    'description' => __('Your Iran SMS Panel (2972.ir) username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Iran SMS Panel (2972.ir) password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the Iran SMS Panel', 'wp-sms'),
                    ],
                    'port' => [
                        'type'        => 'string',
                        'label'       => __('Port', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Optional port number (leave empty if unsure)', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');
        $port     = (string) $this->getChannelConfig('sms', 'port', '0');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('Iran SMS Panel credentials not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('Iran SMS Panel sender not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::SEND_URL, [
            'body' => [
                'username'  => $username,
                'password'  => $password,
                'number'    => $sender,
                'recipient' => $message->getRecipient(),
                'port'      => $port !== '' ? $port : '0',
                'message'   => $message->getBody(),
                'flash'     => false,
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(
                sprintf(__('Iran SMS Panel send failed (HTTP %d)', 'wp-sms'), $result['code']),
            );
        }

        // v7 semantics: empty/falsy body == success, non-empty body == error message.
        $body = trim((string) $result['body']);
        if ($body === '' || $body === '0' || strtolower($body) === 'false') {
            return DeliveryResult::sent();
        }

        return DeliveryResult::failed($body);
    }

    public function getCredit(): ?string
    {
        if (!class_exists('SoapClient')) {
            return null;
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        if ($username === '' || $password === '') {
            return null;
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $auth   = $client->Authentication($username, $password);
            if ((string) $auth !== '1') {
                return null;
            }
            $credit = $client->GetCredit();
        } catch (\Throwable $ex) {
            return null;
        }

        return is_scalar($credit) ? (string) $credit : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required to validate Iran SMS Panel credentials', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $auth   = $client->Authentication($username, $password);
        } catch (\Throwable $ex) {
            return TestConnectionResult::error($ex->getMessage());
        }

        if ((string) $auth !== '1') {
            return TestConnectionResult::error((string) $auth !== '' ? (string) $auth : __('Authentication failed', 'wp-sms'));
        }

        try {
            $credit = $client->GetCredit();
        } catch (\Throwable $ex) {
            return TestConnectionResult::error($ex->getMessage());
        }

        $credit = is_scalar($credit) ? (string) $credit : 'N/A';
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }
}

<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * CandooSms — Iranian SMS panel exposing a SOAP web service.
 *
 * SOAP endpoint: http://my.candoosms.com/services/?wsdl
 *
 * Operations:
 *   Send({ username, password, srcNumber, body, destNo[], flash })
 *     -> string/numeric (message id) or error string
 *   Balance({ username, password })
 *     -> string/numeric remaining credit
 *
 * Auth: account username + password sent in every SOAP request body.
 *
 * The v7 implementation used nusoap; this v8 port uses PHP's built-in
 * SoapClient since the published WSDL is standards-compliant.
 *
 * Out of scope: templates/patterns (not exposed by this SOAP API), MMS,
 * status webhooks, inbound webhooks. Requires the PHP soap extension.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class CandooSmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://my.candoosms.com/services/?wsdl';

    public function getId(): string
    {
        return 'candoosms';
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
                    'description' => __('Your CandooSms panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your CandooSms panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the CandooSms panel', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for the CandooSms gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('CandooSms credentials not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('CandooSms sender not configured', 'wp-sms'));
        }

        $params = [
            'username'  => $username,
            'password'  => $password,
            'srcNumber' => $sender,
            'body'      => $message->getBody(),
            'destNo'    => [$message->getRecipient()],
            'flash'     => '0',
        ];

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client   = new \SoapClient(self::WSDL, [
                'encoding'           => 'UTF-8',
                'exceptions'         => true,
                'connection_timeout' => 30,
            ]);
            $response = $client->Send($params);
        } catch (\SoapFault $ex) {
            return DeliveryResult::failed($ex->getMessage());
        } catch (\Throwable $ex) {
            return DeliveryResult::failed($ex->getMessage());
        }

        return $this->parseResponse($response);
    }

    private function parseResponse(mixed $response): DeliveryResult
    {
        if ($response === null || $response === '' || $response === false) {
            return DeliveryResult::failed(__('CandooSms send failed', 'wp-sms'));
        }

        // Some WSDL bindings wrap the result in a SendResult member.
        if (is_object($response) && isset($response->SendResult)) {
            $response = $response->SendResult;
        }

        // Single-element arrays per recipient: take the first.
        if (is_array($response)) {
            $response = $response[0] ?? '';
        }

        $value = is_scalar($response) ? (string) $response : wp_json_encode($response);
        if ($value === '' || $value === false) {
            return DeliveryResult::failed(__('CandooSms send failed', 'wp-sms'));
        }

        if (is_numeric($value)) {
            return DeliveryResult::sent($value);
        }

        return DeliveryResult::failed($value);
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
            $client = new \SoapClient(self::WSDL, [
                'encoding'           => 'UTF-8',
                'exceptions'         => true,
                'connection_timeout' => 30,
            ]);
            $result = $client->Balance(['username' => $username, 'password' => $password]);
        } catch (\Throwable $ex) {
            return null;
        }

        if (is_object($result) && isset($result->BalanceResult)) {
            $result = $result->BalanceResult;
        }
        return is_scalar($result) ? (string) $result : null;
    }

    public function testConnection(): TestConnectionResult
    {
        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for the CandooSms gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL, [
                'encoding'           => 'UTF-8',
                'exceptions'         => true,
                'connection_timeout' => 30,
            ]);
            $result = $client->Balance(['username' => $username, 'password' => $password]);
        } catch (\SoapFault $ex) {
            return TestConnectionResult::error($ex->getMessage());
        } catch (\Throwable $ex) {
            return TestConnectionResult::error($ex->getMessage());
        }

        if (is_object($result) && isset($result->BalanceResult)) {
            $result = $result->BalanceResult;
        }

        if (!is_scalar($result) || (string) $result === '') {
            return TestConnectionResult::error(__('Unknown error', 'wp-sms'));
        }

        $credit = (string) $result;
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }
}

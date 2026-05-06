<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * SMS Service (mihansmscenter) — Iranian SMS panel exposing a SOAP/WSDL API.
 *
 * SOAP API:
 *   WSDL:    http://mihansmscenter.com/webservice/?wsdl
 *   Send:    multiSend(username, password, to, from, message)
 *   Credit:  accountInfo(username, password)
 *
 * Auth: account username + password sent as named SOAP parameters in every
 * call.
 *
 * Response shape: multiSend returns an object/array with a `status` field —
 * `status === 0` indicates success; any other value (including SoapFault)
 * is treated as failure. accountInfo returns an object with a `balance`
 * integer field.
 *
 * Out of scope (not exposed by the API): MMS, flash SMS, templates, status
 * webhooks, inbound webhooks, opt-out detection.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class SmsServiceProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL_URL = 'http://mihansmscenter.com/webservice/?wsdl';

    public function getId(): string
    {
        return 'smsservice';
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
                    'description' => __('Your SMS Service panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your SMS Service panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the SMS Service panel', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('SMS Service credentials not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('SMS Service sender not configured', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is not enabled on this server', 'wp-sms'));
        }

        try {
            $client = $this->createSoapClient();
            $result = $client->__soapCall('multiSend', [
                'username' => $username,
                'password' => $password,
                'to'       => $message->getRecipient(),
                'from'     => $sender,
                'message'  => $message->getBody(),
            ]);
        } catch (\SoapFault $e) {
            return DeliveryResult::failed($e->getMessage());
        }

        return $this->parseResponse($result);
    }

    private function parseResponse(mixed $result): DeliveryResult
    {
        $status = null;
        if (is_array($result) && isset($result['status'])) {
            $status = (int) $result['status'];
        } elseif (is_object($result) && isset($result->status)) {
            $status = (int) $result->status;
        }

        if ($status === 0) {
            return DeliveryResult::sent('');
        }

        return DeliveryResult::failed(__('SMS Service send failed', 'wp-sms'));
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '' || !class_exists('SoapClient')) {
            return null;
        }

        try {
            $client = $this->createSoapClient();
            $result = $client->__soapCall('accountInfo', [
                'username' => $username,
                'password' => $password,
            ]);
        } catch (\SoapFault) {
            return null;
        }

        $balance = null;
        if (is_object($result) && isset($result->balance)) {
            $balance = $result->balance;
        } elseif (is_array($result) && isset($result['balance'])) {
            $balance = $result['balance'];
        }

        return $balance === null ? null : (string) $balance;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is not enabled on this server', 'wp-sms'));
        }

        try {
            $client = $this->createSoapClient();
            $result = $client->__soapCall('accountInfo', [
                'username' => $username,
                'password' => $password,
            ]);
        } catch (\SoapFault $e) {
            return TestConnectionResult::error($e->getMessage());
        }

        $balance = null;
        if (is_object($result) && isset($result->balance)) {
            $balance = $result->balance;
        } elseif (is_array($result) && isset($result['balance'])) {
            $balance = $result['balance'];
        }

        if ($balance === null) {
            return TestConnectionResult::error(__('Unknown error', 'wp-sms'));
        }

        $credit = (string) $balance;
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }

    private function createSoapClient(): \SoapClient
    {
        return new \SoapClient(self::WSDL_URL, [
            'encoding'   => 'UTF-8',
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
        ]);
    }
}

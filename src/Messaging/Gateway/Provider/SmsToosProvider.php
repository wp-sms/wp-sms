<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * SMS Toos — Iranian SMS panel exposing a SOAP/WSDL API.
 *
 * SOAP API:
 *   WSDL:    http://87.107.121.52/post/send.asmx?wsdl
 *   Send:    SendSms({ username, password, from, to, text, isflash, udh,
 *                      recId, status }) -> SendSmsResult
 *   Credit:  GetCredit({ username, password }) -> GetCreditResult
 *
 * Auth: account username + password sent as named SOAP parameters in every
 * call.
 *
 * Response shape: SendSms returns a wrapper with a `SendSmsResult` field
 * that contains a vendor-specific message id / status string; truthy
 * non-empty value indicates success. GetCredit returns a wrapper with a
 * `GetCreditResult` field containing a bare credit count.
 *
 * Out of scope (not exposed by the API): MMS, flash SMS toggle (passed but
 * not surfaced as a feature), templates, status webhooks, inbound webhooks,
 * opt-out detection.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class SmsToosProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL_URL = 'http://87.107.121.52/post/send.asmx?wsdl';

    public function getId(): string
    {
        return 'smstoos';
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
                    'description' => __('Your SMS Toos panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your SMS Toos panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the SMS Toos panel', 'wp-sms'),
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
            return DeliveryResult::failed(__('SMS Toos credentials not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('SMS Toos sender not configured', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is not enabled on this server', 'wp-sms'));
        }

        try {
            $client = $this->createSoapClient();
            $response = $client->SendSms([
                'username' => $username,
                'password' => $password,
                'from'     => $sender,
                'to'       => $message->getRecipient(),
                'text'     => $message->getBody(),
                'isflash'  => false,
                'udh'      => '',
                'recId'    => [0],
                'status'   => 0x0,
            ]);
        } catch (\SoapFault $e) {
            return DeliveryResult::failed($e->getMessage());
        }

        $result = is_object($response) && isset($response->SendSmsResult) ? $response->SendSmsResult : $response;

        if ($result === null || $result === '' || $result === false) {
            return DeliveryResult::failed(__('SMS Toos send failed', 'wp-sms'));
        }

        return DeliveryResult::sent((string) $result);
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
            $response = $client->GetCredit([
                'username' => $username,
                'password' => $password,
            ]);
        } catch (\SoapFault) {
            return null;
        }

        $result = is_object($response) && isset($response->GetCreditResult) ? $response->GetCreditResult : $response;

        return $result === null || $result === '' ? null : (string) $result;
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
            $response = $client->GetCredit([
                'username' => $username,
                'password' => $password,
            ]);
        } catch (\SoapFault $e) {
            return TestConnectionResult::error($e->getMessage());
        }

        $result = is_object($response) && isset($response->GetCreditResult) ? $response->GetCreditResult : $response;

        if ($result === null || $result === '' || $result === false) {
            return TestConnectionResult::error(__('Unknown error', 'wp-sms'));
        }

        $credit = (string) $result;
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

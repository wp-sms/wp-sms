<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * SMS Melli — Iranian SMS panel exposing a SOAP/WSDL API.
 *
 * SOAP API:
 *   WSDL:    http://smsmelli.com/class/sms/webservice3/server.php?wsdl
 *   Send:    SendSMS(user, pass, fromNum, toNum, messageContent, messageType)
 *   Credit:  GetCredit(user, pass)
 *
 * Auth: account username + password sent as SOAP parameters in every call.
 * The legacy v7 implementation used the bundled `nusoap_client`; v8 uses
 * PHP's built-in SoapClient (php-soap extension) instead.
 *
 * Response shape: SendSMS returns a vendor-specific identifier / status
 * string; truthy non-empty value indicates success. GetCredit returns a
 * bare credit count.
 *
 * Out of scope (not exposed by the API): MMS, flash SMS toggle, templates,
 * status webhooks, inbound webhooks, opt-out detection.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class SmsMelliProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL_URL = 'http://smsmelli.com/class/sms/webservice3/server.php?wsdl';

    public function getId(): string
    {
        return 'smsmelli';
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
                    'description' => __('Your SMS Melli panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your SMS Melli panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the SMS Melli panel', 'wp-sms'),
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
            return DeliveryResult::failed(__('SMS Melli credentials not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('SMS Melli sender not configured', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is not enabled on this server', 'wp-sms'));
        }

        try {
            $client = $this->createSoapClient();
            $result = $client->SendSMS([
                'user'           => $username,
                'pass'           => $password,
                'fromNum'        => $sender,
                'toNum'          => $message->getRecipient(),
                'messageContent' => $message->getBody(),
                'messageType'    => 'normal',
            ]);
        } catch (\SoapFault $e) {
            return DeliveryResult::failed($e->getMessage());
        }

        return $this->parseResponse($result);
    }

    private function parseResponse(mixed $result): DeliveryResult
    {
        if (is_object($result) && isset($result->SendSMSResult)) {
            $result = $result->SendSMSResult;
        }

        if ($result === null || $result === '' || $result === false) {
            return DeliveryResult::failed(__('SMS Melli send failed', 'wp-sms'));
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
            $result = $client->GetCredit(['user' => $username, 'pass' => $password]);
        } catch (\SoapFault) {
            return null;
        }

        if (is_object($result) && isset($result->GetCreditResult)) {
            $result = $result->GetCreditResult;
        }

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
            $result = $client->GetCredit(['user' => $username, 'pass' => $password]);
        } catch (\SoapFault $e) {
            return TestConnectionResult::error($e->getMessage());
        }

        if (is_object($result) && isset($result->GetCreditResult)) {
            $result = $result->GetCreditResult;
        }

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

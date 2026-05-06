<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * TextSMS — Iranian SMS panel exposing a SOAP/WSDL API.
 *
 * Same backend wire format as the sister SSMSS provider (both run on the
 * same `smsService.php` SOAP service):
 *   WSDL:    http://www.textsms.ir/webservice/smsService.php?wsdl
 *   Send:    send_sms(username, password, from, recipients, message)
 *            (recipients = comma-joined list of MSISDNs)
 *   Credit:  sms_credit(username, password)
 *
 * Auth: account username + password sent as positional SOAP parameters in
 * every call.
 *
 * Response shape: send_sms returns a vendor-specific message id / status
 * string; truthy non-empty value indicates success. sms_credit returns a
 * bare credit count.
 *
 * Out of scope (not exposed by the API): MMS, flash SMS, templates, status
 * webhooks, inbound webhooks, opt-out detection.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class TextSmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL_URL = 'http://www.textsms.ir/webservice/smsService.php?wsdl';

    public function getId(): string
    {
        return 'textsms';
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
                    'description' => __('Your TextSMS panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your TextSMS panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the TextSMS panel', 'wp-sms'),
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
            return DeliveryResult::failed(__('TextSMS credentials not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('TextSMS sender not configured', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is not enabled on this server', 'wp-sms'));
        }

        try {
            $client = $this->createSoapClient();
            $result = $client->send_sms(
                $username,
                $password,
                $sender,
                $message->getRecipient(),
                $message->getBody(),
            );
        } catch (\SoapFault $e) {
            return DeliveryResult::failed($e->getMessage());
        }

        if ($result === null || $result === '' || $result === false) {
            return DeliveryResult::failed(__('TextSMS send failed', 'wp-sms'));
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
            $result = $client->sms_credit($username, $password);
        } catch (\SoapFault) {
            return null;
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
            $result = $client->sms_credit($username, $password);
        } catch (\SoapFault $e) {
            return TestConnectionResult::error($e->getMessage());
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

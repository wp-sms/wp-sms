<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * SMS Line — Iranian SMS panel exposing a SOAP/WSDL API.
 *
 * SOAP API:
 *   WSDL:    http://webservice.smsline.ir/index.php?wsdl
 *   Send:    Send_Group_SMS(username, password, recipients, message, from, 1)
 *            (recipients = comma-joined list of MSISDNs; trailing 1 is the
 *            unicode/ASCII flag)
 *   Credit:  CREDIT_LINESMS(username, password, from)
 *
 * Auth: account username + password as positional SOAP parameters.
 *
 * Response shape: Send_Group_SMS returns a vendor-specific message id /
 * status string; truthy non-empty value indicates success.
 * CREDIT_LINESMS returns a bare credit count.
 *
 * Out of scope (not exposed by the API): MMS, flash SMS, templates, status
 * webhooks, inbound webhooks, opt-out detection.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class SmsLineProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL_URL = 'http://webservice.smsline.ir/index.php?wsdl';

    public function getId(): string
    {
        return 'smsline';
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
                    'description' => __('Your SMS Line panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your SMS Line panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the SMS Line panel', 'wp-sms'),
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
            return DeliveryResult::failed(__('SMS Line credentials not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('SMS Line sender not configured', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is not enabled on this server', 'wp-sms'));
        }

        try {
            $client = $this->createSoapClient();
            $result = $client->Send_Group_SMS(
                $username,
                $password,
                $message->getRecipient(),
                $message->getBody(),
                $sender,
                1,
            );
        } catch (\SoapFault $e) {
            return DeliveryResult::failed($e->getMessage());
        }

        return $this->parseResponse($result);
    }

    private function parseResponse(mixed $result): DeliveryResult
    {
        if ($result === null || $result === '' || $result === false) {
            return DeliveryResult::failed(__('SMS Line send failed', 'wp-sms'));
        }

        return DeliveryResult::sent((string) $result);
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '' || !class_exists('SoapClient')) {
            return null;
        }

        try {
            $client = $this->createSoapClient();
            $result = $client->CREDIT_LINESMS($username, $password, $sender);
        } catch (\SoapFault) {
            return null;
        }

        return $result === null || $result === '' ? null : (string) $result;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is not enabled on this server', 'wp-sms'));
        }

        try {
            $client = $this->createSoapClient();
            $result = $client->CREDIT_LINESMS($username, $password, $sender);
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

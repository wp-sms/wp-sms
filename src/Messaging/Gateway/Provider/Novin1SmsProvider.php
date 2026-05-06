<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Novin1SMS — Iranian SMS panel exposing a SOAP web service.
 *
 * SOAP API (WSDL): http://www.novin1sms.ir/webservice/smsService.php?wsdl
 *   Send:   send_sms(username, password, from, to, message)
 *           → numeric tracking ID on success, error code/string on failure.
 *           Recipients are passed as a comma-separated string.
 *   Credit: sms_credit(username, password)
 *           → bare credit string.
 *
 * Auth: account username + password supplied as positional SOAP parameters.
 *
 * Out of scope (not exposed by the API): provider-managed templates / patterns,
 * MMS, status webhooks, inbound webhooks, opt-out detection.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class Novin1SmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://www.novin1sms.ir/webservice/smsService.php?wsdl';

    public function getId(): string
    {
        return 'novin1sms';
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
                    'description' => __('Your Novin1SMS account username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Novin1SMS account password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the Novin1SMS panel', 'wp-sms'),
                        'placeholder' => '5000xxxxxxxx',
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
            return DeliveryResult::failed(__('Novin1SMS credentials not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('Novin1SMS sender not configured', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for Novin1SMS', 'wp-sms'));
        }

        try {
            $client = $this->soapClient();
            $response = $client->send_sms(
                $username,
                $password,
                $sender,
                $message->getRecipient(),
                $message->getBody(),
            );
        } catch (\SoapFault $e) {
            return DeliveryResult::failed($e->faultstring ?: $e->getMessage());
        } catch (\Throwable $e) {
            return DeliveryResult::failed($e->getMessage());
        }

        return $this->interpretSendResult($response);
    }

    private function interpretSendResult(mixed $response): DeliveryResult
    {
        if (is_object($response)) {
            $response = $response->return ?? $response;
        }
        if (is_array($response)) {
            $response = reset($response);
        }

        if ($response === null || $response === false || $response === '') {
            return DeliveryResult::failed(__('Novin1SMS send failed', 'wp-sms'));
        }

        $value = (string) $response;
        if (is_numeric($value) && (int) $value > 0) {
            return DeliveryResult::sent($value);
        }

        return DeliveryResult::failed($value);
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        if ($username === '' || $password === '') {
            return null;
        }

        if (!class_exists('SoapClient')) {
            return null;
        }

        try {
            $client = $this->soapClient();
            $response = $client->sms_credit($username, $password);
        } catch (\Throwable $e) {
            return null;
        }

        return $this->extractCredit($response);
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for Novin1SMS', 'wp-sms'));
        }

        try {
            $client = $this->soapClient();
            $response = $client->sms_credit($username, $password);
        } catch (\SoapFault $e) {
            return TestConnectionResult::error($e->faultstring ?: $e->getMessage());
        } catch (\Throwable $e) {
            return TestConnectionResult::error($e->getMessage());
        }

        $credit = $this->extractCredit($response);
        if ($credit === null) {
            return TestConnectionResult::error(__('Unexpected response from Novin1SMS', 'wp-sms'));
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }

    private function extractCredit(mixed $response): ?string
    {
        if (is_object($response)) {
            $response = $response->return ?? $response;
        }
        if (is_array($response)) {
            $response = reset($response);
        }
        if ($response === null || $response === false) {
            return null;
        }
        return (string) $response;
    }

    private function soapClient(): \SoapClient
    {
        @ini_set('soap.wsdl_cache_enabled', '0');
        return new \SoapClient(self::WSDL, [
            'exceptions' => true,
            'encoding'   => 'UTF-8',
        ]);
    }
}

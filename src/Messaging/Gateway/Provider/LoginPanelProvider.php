<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * LoginPanel — Iranian SMS panel exposing a SOAP web service.
 *
 * SOAP API (WSDL): http://www.loginpanel.ir/post/send.asmx?wsdl
 *   Send:    SendSms({ username, password, from, to, text, isflash, udh, recId, status })
 *            → SendSmsResult — string (numeric tracking ID on success).
 *   Credit:  GetCredit({ username, password })
 *            → GetCreditResult — bare credit string.
 *
 * Auth: account username + password supplied as SOAP parameters.
 *
 * Out of scope (not exposed by the API): provider-managed templates / patterns,
 * MMS, status webhooks, inbound webhooks, opt-out detection. Flash SMS is
 * advertised by the panel as disabled by default.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class LoginPanelProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://www.loginpanel.ir/post/send.asmx?wsdl';

    public function getId(): string
    {
        return 'loginpanel';
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
                    'description' => __('Your LoginPanel account username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your LoginPanel account password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the LoginPanel panel', 'wp-sms'),
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
            return DeliveryResult::failed(__('LoginPanel credentials not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('LoginPanel sender not configured', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for LoginPanel', 'wp-sms'));
        }

        try {
            $client = $this->soapClient();
            $response = $client->SendSms([
                'username' => $username,
                'password' => $password,
                'from'     => $sender,
                'to'       => [$message->getRecipient()],
                'text'     => $message->getBody(),
                'isflash'  => false,
                'udh'      => '',
                'recId'    => [0],
                'status'   => 0,
            ]);
        } catch (\SoapFault $e) {
            return DeliveryResult::failed($e->faultstring ?: $e->getMessage());
        } catch (\Throwable $e) {
            return DeliveryResult::failed($e->getMessage());
        }

        $result = (string) ($response->SendSmsResult ?? '');

        // Numeric tracking ID indicates success; any non-numeric / empty value is a fault.
        if ($result === '' || !is_numeric($result) || (int) $result <= 0) {
            return DeliveryResult::failed(
                $result !== '' ? $result : __('LoginPanel send failed', 'wp-sms')
            );
        }

        return DeliveryResult::sent($result);
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
            $response = $client->GetCredit([
                'username' => $username,
                'password' => $password,
            ]);
        } catch (\Throwable $e) {
            return null;
        }

        $credit = $response->GetCreditResult ?? null;
        if ($credit === null) {
            return null;
        }

        return (string) $credit;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for LoginPanel', 'wp-sms'));
        }

        try {
            $client = $this->soapClient();
            $response = $client->GetCredit([
                'username' => $username,
                'password' => $password,
            ]);
        } catch (\SoapFault $e) {
            return TestConnectionResult::error($e->faultstring ?: $e->getMessage());
        } catch (\Throwable $e) {
            return TestConnectionResult::error($e->getMessage());
        }

        $credit = $response->GetCreditResult ?? null;
        if ($credit === null) {
            return TestConnectionResult::error(__('Unexpected response from LoginPanel', 'wp-sms'));
        }

        $creditStr = (string) $credit;
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $creditStr),
            ['credit' => $creditStr],
        );
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

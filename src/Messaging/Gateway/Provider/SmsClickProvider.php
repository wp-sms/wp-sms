<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * SMSClick — Iranian SMS gateway exposing a SOAP webservice at smsclick.ir.
 *
 *   WSDL:    http://smsclick.ir/post/send.asmx?wsdl
 *   Send:    SendSms({ username, password, from, to, text, isflash, udh, recId, status })
 *   Credit:  GetCredit({ username, password }) -> { GetCreditResult }
 *
 * Auth: account username + password sent inside the SOAP request body.
 * Response: scalar return value from SendSms; numeric credit from GetCredit.
 *
 * Out of scope (not exposed by the API): MMS, delivery webhooks, inbound
 * webhooks, template/pattern messaging, and opt-out detection. Flash SMS is
 * supported by the underlying API but is not wired into the v8 send path.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class SmsClickProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://smsclick.ir/post/send.asmx?wsdl';

    public function getId(): string
    {
        return 'smsclick';
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
                    'description' => __('Your SMSClick panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your SMSClick panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the SMSClick panel', 'wp-sms'),
                        'placeholder' => '3000xxxxxxxx',
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
            return DeliveryResult::failed(__('SMSClick credentials not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('SMSClick sender not configured', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for SMSClick', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL);

            $result = $client->SendSms([
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

            if ($result === null || $result === false) {
                return DeliveryResult::failed(__('SMSClick send failed', 'wp-sms'));
            }

            // SendSms returns either a scalar or a wrapper object — try both.
            $value = is_object($result) && isset($result->SendSmsResult) ? $result->SendSmsResult : $result;

            if ($value === null || $value === false || $value === '') {
                return DeliveryResult::failed(__('SMSClick send failed', 'wp-sms'));
            }

            return DeliveryResult::sent((string) (is_scalar($value) ? $value : ''));
        } catch (\SoapFault $e) {
            return DeliveryResult::failed($e->faultstring ?: $e->getMessage());
        } catch (\Throwable $e) {
            return DeliveryResult::failed($e->getMessage());
        }
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        if ($username === '' || $password === '' || !class_exists('SoapClient')) {
            return null;
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL);
            $response = $client->GetCredit([
                'username' => $username,
                'password' => $password,
            ]);

            $credit = $response->GetCreditResult ?? null;
            return $credit !== null ? (string) $credit : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for SMSClick', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL);
            $response = $client->GetCredit([
                'username' => $username,
                'password' => $password,
            ]);

            $credit = $response->GetCreditResult ?? null;
            if ($credit === null) {
                return TestConnectionResult::error(__('Unexpected response from SMSClick', 'wp-sms'));
            }

            $creditStr = (string) $credit;
            return TestConnectionResult::ok(
                sprintf(__('Connected — Credit: %s', 'wp-sms'), $creditStr),
                ['credit' => $creditStr],
            );
        } catch (\SoapFault $e) {
            return TestConnectionResult::error($e->faultstring ?: $e->getMessage());
        } catch (\Throwable $e) {
            return TestConnectionResult::error($e->getMessage());
        }
    }
}

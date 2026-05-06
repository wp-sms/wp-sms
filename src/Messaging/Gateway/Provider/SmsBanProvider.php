<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * SMSBan — Iranian SMS gateway exposing a SOAP webservice at smsban.ir.
 *
 *   WSDL:    http://smsban.ir/API/SendSMS.asmx?WSDL
 *   Send:    Send_Sms4(USERNAME, PASSWORD, TO, FROM, TEXT, API, API_CHANGE_ALLOW, FLASH, Internation)
 *   Credit:  CHECK_CREDIT(USERNAME, PASSWORD)
 *
 * Auth: account username + password sent as SOAP arguments.
 * Response: scalar values returned via *Result accessors on the SOAP response.
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
class SmsBanProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://smsban.ir/API/SendSMS.asmx?WSDL';

    public function getId(): string
    {
        return 'smsban';
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
                    'description' => __('Your SMSBan panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your SMSBan panel password', 'wp-sms'),
                ],
                'api_key'  => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Optional API key issued by SMSBan; leave blank if not provided', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the SMSBan panel', 'wp-sms'),
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
            return DeliveryResult::failed(__('SMSBan credentials not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('SMSBan sender not configured', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for SMSBan', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL);

            $response = $client->Send_Sms4([
                'USERNAME'         => $username,
                'PASSWORD'         => $password,
                'TO'               => [$message->getRecipient()],
                'FROM'             => $sender,
                'TEXT'             => $message->getBody(),
                'API'              => (string) $this->getSharedConfig('api_key', ''),
                'API_CHANGE_ALLOW' => 1,
                'FLASH'            => false,
                'Internation'      => false,
            ]);

            $result = $response->Send_Sms4Result ?? null;
            return $this->parseSendResult($result);
        } catch (\SoapFault $e) {
            return DeliveryResult::failed($e->faultstring ?: $e->getMessage());
        } catch (\Throwable $e) {
            return DeliveryResult::failed($e->getMessage());
        }
    }

    private function parseSendResult(mixed $result): DeliveryResult
    {
        if ($result === null || $result === false || $result === '') {
            return DeliveryResult::failed(__('SMSBan send failed', 'wp-sms'));
        }

        // SMSBan's Send_Sms4 returns a numeric status / message id; numeric > 0
        // is treated as success per panel conventions.
        if (is_numeric($result) && (int) $result <= 0) {
            return DeliveryResult::failed((string) $result);
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
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL);
            $response = $client->CHECK_CREDIT([
                'USERNAME' => $username,
                'PASSWORD' => $password,
            ]);

            $credit = $response->CHECK_CREDITResult ?? null;
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
            return TestConnectionResult::error(__('PHP SOAP extension is required for SMSBan', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL);
            $response = $client->CHECK_CREDIT([
                'USERNAME' => $username,
                'PASSWORD' => $password,
            ]);

            $credit = $response->CHECK_CREDITResult ?? null;
            if ($credit === null) {
                return TestConnectionResult::error(__('Unexpected response from SMSBan', 'wp-sms'));
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

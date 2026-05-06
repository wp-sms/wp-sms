<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * PersianSMS — Iranian SMS panel exposing a SOAP web service.
 *
 * SOAP endpoint: http://persian-sms.com/API/SendSMS.asmx?WSDL
 *
 * Operations:
 *   Send_Sms4({ USERNAME, PASSWORD, TO, FROM, TEXT, API, API_CHANGE_ALLOW, FLASH, Internation })
 *     -> Send_Sms4Result (string/numeric message id, or error string)
 *   CHECK_CREDIT({ USERNAME, PASSWORD })
 *     -> CHECK_CREDITResult (numeric credit)
 *
 * Auth: account username + password sent in every SOAP request body. The v7
 * source also threads a per-request "API" token (separate from credentials);
 * we keep it as an optional shared config field.
 *
 * Out of scope: templates/patterns (not exposed by this SOAP API), MMS, flash SMS,
 * status webhooks, inbound webhooks. Requires the PHP soap extension.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class PersianSmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://persian-sms.com/API/SendSMS.asmx?WSDL';

    public function getId(): string
    {
        return 'persiansms';
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
                    'description' => __('Your PersianSMS panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your PersianSMS panel password', 'wp-sms'),
                ],
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Optional API token issued by the PersianSMS panel (sent as the "API" field on each request)', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the PersianSMS panel', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for the PersianSMS gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $apiToken = (string) $this->getSharedConfig('api_key', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('PersianSMS credentials not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('PersianSMS sender not configured', 'wp-sms'));
        }

        $params = [
            'USERNAME'         => $username,
            'PASSWORD'         => $password,
            'TO'               => [$message->getRecipient()],
            'FROM'             => $sender,
            'TEXT'             => $message->getBody(),
            'API'              => $apiToken,
            'API_CHANGE_ALLOW' => 1,
            'FLASH'            => false,
            'Internation'      => false,
        ];

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->Send_Sms4($params);
        } catch (\SoapFault $ex) {
            return DeliveryResult::failed($ex->getMessage());
        } catch (\Throwable $ex) {
            return DeliveryResult::failed($ex->getMessage());
        }

        return $this->parseResponse($response->Send_Sms4Result ?? null);
    }

    private function parseResponse(mixed $result): DeliveryResult
    {
        if ($result === null || $result === '') {
            return DeliveryResult::failed(__('PersianSMS send failed', 'wp-sms'));
        }

        $value = is_scalar($result) ? (string) $result : wp_json_encode($result);

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
            $client = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->CHECK_CREDIT(['USERNAME' => $username, 'PASSWORD' => $password]);
        } catch (\Throwable $ex) {
            return null;
        }

        $credit = $response->CHECK_CREDITResult ?? null;
        return $credit !== null ? (string) $credit : null;
    }

    public function testConnection(): TestConnectionResult
    {
        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for the PersianSMS gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->CHECK_CREDIT(['USERNAME' => $username, 'PASSWORD' => $password]);
        } catch (\SoapFault $ex) {
            return TestConnectionResult::error($ex->getMessage());
        } catch (\Throwable $ex) {
            return TestConnectionResult::error($ex->getMessage());
        }

        $credit = $response->CHECK_CREDITResult ?? null;
        if ($credit === null) {
            return TestConnectionResult::error(__('Unknown error', 'wp-sms'));
        }

        $credit = (string) $credit;
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }
}

<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Jahan Payamak — Iranian SMS panel exposing a SOAP web service.
 *
 * SOAP endpoint: http://jahanpayamak.ir/API/SendSMS.asmx?WSDL
 *
 * Operations:
 *   Send_Sms4({ USERNAME, PASSWORD, TO, FROM, TEXT, API, API_CHANGE_ALLOW, FLASH, Internation })
 *     -> Send_Sms4Result (string/numeric message id, or error string)
 *   CHECK_CREDIT({ USERNAME, PASSWORD })
 *     -> CHECK_CREDITResult (numeric credit)
 *
 * The "API" parameter is selected from the sender number prefix:
 *   1000xxxx -> API=11, 2000xxxx -> API=22, 3000xxxx -> API=13.
 * Unknown prefixes default to API=11 (matches the legacy v7 default behaviour
 * where $api was only set inside the if/else-if cascade).
 *
 * Out of scope: templates/patterns (not exposed), MMS, status webhooks,
 * inbound webhooks. Requires the PHP soap extension.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class JahanPayamakProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://jahanpayamak.ir/API/SendSMS.asmx?WSDL';

    public function getId(): string
    {
        return 'jahanpayamak';
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
                    'description' => __('Your Jahan Payamak panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Jahan Payamak panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the Jahan Payamak panel (1000/2000/3000 prefix selects the API line type)', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for the Jahan Payamak gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('Jahan Payamak credentials not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('Jahan Payamak sender not configured', 'wp-sms'));
        }

        $params = [
            'USERNAME'         => $username,
            'PASSWORD'         => $password,
            'TO'               => $message->getRecipient(),
            'FROM'             => $sender,
            'TEXT'             => $message->getBody(),
            'API'              => $this->resolveApiCode($sender),
            'API_CHANGE_ALLOW' => 1,
            'FLASH'            => false,
            'Internation'      => false,
        ];

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client   = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->Send_Sms4($params);
        } catch (\Throwable $ex) {
            return DeliveryResult::failed($ex->getMessage());
        }

        return $this->parseResponse($response->Send_Sms4Result ?? null, __('Jahan Payamak send failed', 'wp-sms'));
    }

    private function resolveApiCode(string $sender): int
    {
        $prefix = substr($sender, 0, 4);
        return match ($prefix) {
            '1000'  => 11,
            '2000'  => 22,
            '3000'  => 13,
            default => 11,
        };
    }

    private function parseResponse(mixed $result, string $defaultError): DeliveryResult
    {
        if ($result === null || $result === '') {
            return DeliveryResult::failed($defaultError);
        }

        $value = is_scalar($result) ? (string) $result : wp_json_encode($result);

        if (is_numeric($value) && (int) $value > 0) {
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
            $client   = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
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
            return TestConnectionResult::error(__('PHP SOAP extension is required for the Jahan Payamak gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client   = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->CHECK_CREDIT(['USERNAME' => $username, 'PASSWORD' => $password]);
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

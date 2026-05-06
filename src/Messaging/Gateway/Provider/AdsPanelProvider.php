<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * AdsPanel — Iranian SMS panel exposing a SOAP web service.
 *
 * SOAP endpoint: http://adspanel.ir/webservice/server.asmx?wsdl
 *
 * Operations (positional argument lists):
 *   Sendsms([ api_key, from, username, password, mcc, text, to_csv, isflash ])
 *     -> string. The v7 source treats a comma-separated response whose first
 *        element is "1" as success; everything else is an error string.
 *   Credit([ 2, username, password ])
 *     -> string. Values "301" / "302" indicate auth/credit errors.
 *
 * Auth: account username + password sent in every SOAP request, plus an API
 * key prepended as the first positional argument to Sendsms.
 *
 * Out of scope: templates/patterns (not exposed by this SOAP API), MMS,
 * status webhooks, inbound webhooks. Requires the PHP soap extension.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class AdsPanelProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://adspanel.ir/webservice/server.asmx?wsdl';

    public function getId(): string
    {
        return 'adspanel';
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
                    'description' => __('Your AdsPanel account username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your AdsPanel account password', 'wp-sms'),
                ],
                'api_key'  => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('API key issued by the AdsPanel panel', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the AdsPanel panel', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for the AdsPanel gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $apiKey   = (string) $this->getSharedConfig('api_key', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('AdsPanel credentials not configured', 'wp-sms'));
        }
        if ($apiKey === '') {
            return DeliveryResult::failed(__('AdsPanel API Key not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('AdsPanel sender not configured', 'wp-sms'));
        }

        $args = [
            $apiKey,
            $sender,
            $username,
            $password,
            (string) get_option('wp_sms_mcc'),
            $message->getBody(),
            $message->getRecipient(),
            false,
        ];

        return $this->callSoap('Sendsms', $args, function ($response) {
            $value = is_scalar($response) ? (string) $response : '';
            $parts = explode(',', $value);
            if (count($parts) > 1 && (string) $parts[0] === '1') {
                return DeliveryResult::sent((string) ($parts[1] ?? ''));
            }
            return DeliveryResult::failed($value !== '' ? $value : __('AdsPanel send failed', 'wp-sms'));
        });
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
            $result = $client->Credit([2, $username, $password]);
        } catch (\Throwable $ex) {
            return null;
        }

        $value = is_scalar($result) ? (string) $result : '';
        if ($value === '' || $value === '301' || $value === '302') {
            return null;
        }
        return $value;
    }

    public function testConnection(): TestConnectionResult
    {
        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for the AdsPanel gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $result = $client->Credit([2, $username, $password]);
        } catch (\SoapFault $ex) {
            return TestConnectionResult::error($ex->getMessage());
        } catch (\Throwable $ex) {
            return TestConnectionResult::error($ex->getMessage());
        }

        $value = is_scalar($result) ? (string) $result : '';
        if ($value === '' || $value === '301' || $value === '302') {
            return TestConnectionResult::error($value !== '' ? $value : __('Unknown error', 'wp-sms'));
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $value),
            ['credit' => $value],
        );
    }

    /**
     * Invoke a SOAP operation and translate any failure into a DeliveryResult.
     *
     * @param callable(mixed): DeliveryResult $onSuccess
     */
    private function callSoap(string $operation, array $params, callable $onSuccess): DeliveryResult
    {
        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client   = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->$operation($params);
        } catch (\SoapFault $ex) {
            return DeliveryResult::failed($ex->getMessage());
        } catch (\Throwable $ex) {
            return DeliveryResult::failed($ex->getMessage());
        }

        return $onSuccess($response);
    }
}

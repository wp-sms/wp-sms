<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Bestit — Iranian SMS panel exposing a SOAP web service.
 *
 * SOAP endpoint: http://panelsms.bestit.co/WsSms.asmx?wsdl
 *
 * Operations:
 *   sendsms({ username, password, to (csv), text, from, api })
 *     -> sendsmsResult.long. Numeric codes 1000–1010 indicate provider errors;
 *        other values are treated as message identifiers / success markers.
 *   Credites({ username, password })
 *     -> CreditesResult (numeric credit)
 *
 * Auth: account username + password sent in every SOAP request body, plus
 * an API key passed as the `api` field on sendsms.
 *
 * Out of scope: templates/patterns (not exposed by this SOAP API), MMS,
 * status webhooks, inbound webhooks. Requires the PHP soap extension.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class BestitProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://panelsms.bestit.co/WsSms.asmx?wsdl';

    /** sendsmsResult.long values that the v7 source treated as send failures. */
    private const ERROR_CODES = [
        '1000', '1001', '1002', '1003', '1004', '1005',
        '1006', '1007', '1008', '1009', '1010',
    ];

    public function getId(): string
    {
        return 'bestit';
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
                    'description' => __('Your Bestit panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Bestit panel password', 'wp-sms'),
                ],
                'api_key'  => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('API key issued by the Bestit panel', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the Bestit panel', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for the Bestit gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $apiKey   = (string) $this->getSharedConfig('api_key', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('Bestit credentials not configured', 'wp-sms'));
        }
        if ($apiKey === '') {
            return DeliveryResult::failed(__('Bestit API Key not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('Bestit sender not configured', 'wp-sms'));
        }

        $params = [
            'username' => $username,
            'password' => $password,
            'to'       => $message->getRecipient(),
            'text'     => $message->getBody(),
            'from'     => $sender,
            'api'      => $apiKey,
        ];

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client   = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->sendsms($params);
        } catch (\SoapFault $ex) {
            return DeliveryResult::failed($ex->getMessage());
        } catch (\Throwable $ex) {
            return DeliveryResult::failed($ex->getMessage());
        }

        return $this->parseResponse($response);
    }

    private function parseResponse(mixed $response): DeliveryResult
    {
        $value = $response->sendsmsResult->long ?? null;
        if ($value === null) {
            return DeliveryResult::failed(__('Bestit send failed', 'wp-sms'));
        }

        $string = (string) $value;
        if (in_array($string, self::ERROR_CODES, true)) {
            return DeliveryResult::failed(sprintf(__('Bestit send failed (code %s)', 'wp-sms'), $string));
        }

        return DeliveryResult::sent($string);
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
            $response = $client->Credites(['username' => $username, 'password' => $password]);
        } catch (\Throwable $ex) {
            return null;
        }

        $credit = $response->CreditesResult ?? null;
        return $credit !== null ? (string) $credit : null;
    }

    public function testConnection(): TestConnectionResult
    {
        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for the Bestit gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client   = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->Credites(['username' => $username, 'password' => $password]);
        } catch (\SoapFault $ex) {
            return TestConnectionResult::error($ex->getMessage());
        } catch (\Throwable $ex) {
            return TestConnectionResult::error($ex->getMessage());
        }

        $credit = $response->CreditesResult ?? null;
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

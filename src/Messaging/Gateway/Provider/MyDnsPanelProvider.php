<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * MyDnsPanel — Iranian SMS panel exposing a SOAP web service.
 *
 * SOAP endpoint: http://mydnspanel.com/webservice/server.asmx?wsdl
 *
 * Operations:
 *   Sendsms(api_key, from, username, password, mcc, message, to, false)
 *     -> string (comma-separated "<status>,<message_id>" pairs; status == 1 is success)
 *   Credit(2, username, password)
 *     -> string (numeric credit, or "301"/"302" error codes)
 *
 * Auth: account username + password sent as positional SOAP arguments,
 * plus an API key prefix on every send call. The v7 implementation depended
 * on the bundled `nusoap_client`; this port uses PHP's built-in `SoapClient`
 * against the same WSDL.
 *
 * Out of scope: templates/patterns (not exposed by this SOAP API), MMS,
 * status webhooks, inbound webhooks, the MCC/global-option lookup that v7
 * read from `wp_sms_mcc` (not part of the v8 settings model). Requires the
 * PHP soap extension.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class MyDnsPanelProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://mydnspanel.com/webservice/server.asmx?wsdl';

    public function getId(): string
    {
        return 'mydnspanel';
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
                    'description' => __('Your MyDnsPanel account username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your MyDnsPanel account password', 'wp-sms'),
                ],
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('API key generated from the MyDnsPanel account', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the MyDnsPanel account', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for the MyDnsPanel gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $apiKey   = (string) $this->getSharedConfig('api_key', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('MyDnsPanel credentials not configured', 'wp-sms'));
        }
        if ($apiKey === '') {
            return DeliveryResult::failed(__('MyDnsPanel API key not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('MyDnsPanel sender not configured', 'wp-sms'));
        }

        // The legacy SOAP service takes positional arguments. The fifth parameter
        // is an MCC/network code that v7 read from a separate option; v8 omits
        // that knob and passes an empty string, which the panel accepts as default.
        $args = [
            $apiKey,
            $sender,
            $username,
            $password,
            '',
            $message->getBody(),
            $message->getRecipient(),
            false,
        ];

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client   = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->Sendsms(...$args);
        } catch (\SoapFault $ex) {
            return DeliveryResult::failed($ex->getMessage());
        } catch (\Throwable $ex) {
            return DeliveryResult::failed($ex->getMessage());
        }

        return $this->parseSendResponse($response);
    }

    private function parseSendResponse(mixed $response): DeliveryResult
    {
        if ($response === null || $response === '' || $response === false) {
            return DeliveryResult::failed(__('MyDnsPanel send failed', 'wp-sms'));
        }

        if (!is_scalar($response)) {
            return DeliveryResult::failed(__('Unexpected response from MyDnsPanel', 'wp-sms'));
        }

        $parts  = explode(',', (string) $response);
        $status = $parts[0] ?? '';

        if ((string) $status === '1') {
            $providerId = $parts[1] ?? '';
            return DeliveryResult::sent((string) $providerId);
        }

        return DeliveryResult::failed((string) $response);
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
            $response = $client->Credit(2, $username, $password);
        } catch (\Throwable $ex) {
            return null;
        }

        if (!is_scalar($response)) {
            return null;
        }

        $value = (string) $response;
        // Documented error codes from v7: 301 (auth) and 302 (account state).
        if ($value === '301' || $value === '302') {
            return null;
        }

        return $value;
    }

    public function testConnection(): TestConnectionResult
    {
        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for the MyDnsPanel gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client   = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->Credit(2, $username, $password);
        } catch (\SoapFault $ex) {
            return TestConnectionResult::error($ex->getMessage());
        } catch (\Throwable $ex) {
            return TestConnectionResult::error($ex->getMessage());
        }

        if (!is_scalar($response)) {
            return TestConnectionResult::error(__('Unknown error', 'wp-sms'));
        }

        $value = (string) $response;
        if ($value === '301' || $value === '302') {
            return TestConnectionResult::error(
                sprintf(__('MyDnsPanel rejected the credentials (code %s)', 'wp-sms'), $value),
            );
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $value !== '' ? $value : 'N/A'),
            ['credit' => $value],
        );
    }
}

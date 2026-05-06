<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * MD Panel (ippanel.com) — Iranian SMS panel exposing a legacy SOAP service.
 *
 * SOAP endpoint: http://188.0.240.110/class/sms/wssimple/server.php?wsdl
 *
 * Operations:
 *   SendSMS({ Username, Password, SenderNumber, RecipientNumbers, Message, Type })
 *     -> string (panel-specific scalar; truthy = accepted)
 *   GetCredit({ Username, Password })
 *     -> string (numeric credit)
 *
 * Note: the WSDL host is a bare IP address (no TLS / DNS name), preserved
 * verbatim from v7. The v7 implementation depended on the bundled `nusoap_client`;
 * this port uses PHP's built-in `SoapClient` against the same WSDL.
 *
 * Auth: account username + password sent in every SOAP request body.
 *
 * Out of scope: templates/patterns (not exposed by this legacy SOAP API),
 * MMS, status webhooks, inbound webhooks. Requires the PHP soap extension.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class MdPanelProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://188.0.240.110/class/sms/wssimple/server.php?wsdl';

    public function getId(): string
    {
        return 'mdpanel';
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
                    'description' => __('Your MD Panel (ippanel.com) account username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your MD Panel account password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the MD Panel account', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for the MD Panel gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('MD Panel credentials not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('MD Panel sender not configured', 'wp-sms'));
        }

        $params = [
            'Username'         => $username,
            'Password'         => $password,
            'SenderNumber'     => $sender,
            'RecipientNumbers' => [$message->getRecipient()],
            'Message'          => $message->getBody(),
            'Type'             => 'normal',
        ];

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->SendSMS($params);
        } catch (\SoapFault $ex) {
            return DeliveryResult::failed($ex->getMessage());
        } catch (\Throwable $ex) {
            return DeliveryResult::failed($ex->getMessage());
        }

        return $this->parseResponse($response);
    }

    private function parseResponse(mixed $result): DeliveryResult
    {
        if ($result === null || $result === '' || $result === false) {
            return DeliveryResult::failed(__('MD Panel send failed', 'wp-sms'));
        }

        $value = is_scalar($result) ? (string) $result : wp_json_encode($result);

        if (is_numeric($value) && (int) $value > 0) {
            return DeliveryResult::sent($value);
        }

        // The MD Panel SOAP service returns truthy scalars on success — preserve
        // any non-empty non-numeric token as the provider message id.
        if ($value !== '' && $value !== '0') {
            return DeliveryResult::sent($value);
        }

        return DeliveryResult::failed(__('MD Panel send failed', 'wp-sms'));
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
            $response = $client->GetCredit(['Username' => $username, 'Password' => $password]);
        } catch (\Throwable $ex) {
            return null;
        }

        if ($response === null || $response === '' || $response === false) {
            return null;
        }

        return is_scalar($response) ? (string) $response : null;
    }

    public function testConnection(): TestConnectionResult
    {
        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for the MD Panel gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->GetCredit(['Username' => $username, 'Password' => $password]);
        } catch (\SoapFault $ex) {
            return TestConnectionResult::error($ex->getMessage());
        } catch (\Throwable $ex) {
            return TestConnectionResult::error($ex->getMessage());
        }

        if ($response === null || $response === '' || $response === false) {
            return TestConnectionResult::error(__('Unknown error', 'wp-sms'));
        }

        $credit = is_scalar($response) ? (string) $response : '';
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit !== '' ? $credit : 'N/A'),
            ['credit' => $credit],
        );
    }
}

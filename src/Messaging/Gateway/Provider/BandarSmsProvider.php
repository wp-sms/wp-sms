<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * BandarSms — Iranian SMS panel exposing a "wssimple" SOAP service.
 *
 * SOAP endpoint: http://bandarsms.ir/class/sms/wssimple/server.php?wsdl
 *
 * Operations:
 *   SendSMS({ Username, Password, SenderNumber, RecipientNumbers[], Message, Type })
 *     -> string/numeric (message id) or error string
 *   GetCredit({ Username, Password })
 *     -> string/numeric credit
 *
 * Auth: account username + password sent in every SOAP request body.
 *
 * The v7 implementation used nusoap; this v8 port uses PHP's built-in
 * SoapClient since the wssimple WSDL is standards-compliant.
 *
 * Out of scope: templates/patterns (not exposed by this SOAP API), MMS,
 * status webhooks, inbound webhooks. Requires the PHP soap extension.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class BandarSmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://bandarsms.ir/class/sms/wssimple/server.php?wsdl';

    public function getId(): string
    {
        return 'bandarsms';
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
                    'description' => __('Your BandarSms panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your BandarSms panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the BandarSms panel', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for the BandarSms gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('BandarSms credentials not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('BandarSms sender not configured', 'wp-sms'));
        }

        $params = [
            'Username'         => $username,
            'Password'         => $password,
            'SenderNumber'     => $sender,
            'RecipientNumbers' => [$message->getRecipient()],
            'Message'          => $message->getBody(),
            'Type'             => 'normal',
        ];

        return $this->callSoap('SendSMS', $params, function ($response) {
            return $this->parseResponse($response, __('BandarSms send failed', 'wp-sms'));
        });
    }

    private function parseResponse(mixed $result, string $defaultError): DeliveryResult
    {
        if ($result === null || $result === '' || $result === false) {
            return DeliveryResult::failed($defaultError);
        }

        $value = is_scalar($result) ? (string) $result : wp_json_encode($result);
        if ($value === '' || $value === false) {
            return DeliveryResult::failed($defaultError);
        }

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
            $client = new \SoapClient(self::WSDL, [
                'encoding'           => 'UTF-8',
                'exceptions'         => true,
                'connection_timeout' => 30,
            ]);
            $result = $client->GetCredit([
                'Username' => $username,
                'Password' => $password,
            ]);
        } catch (\Throwable $ex) {
            return null;
        }

        return is_scalar($result) ? (string) $result : null;
    }

    public function testConnection(): TestConnectionResult
    {
        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for the BandarSms gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL, [
                'encoding'           => 'UTF-8',
                'exceptions'         => true,
                'connection_timeout' => 30,
            ]);
            $result = $client->GetCredit([
                'Username' => $username,
                'Password' => $password,
            ]);
        } catch (\SoapFault $ex) {
            return TestConnectionResult::error($ex->getMessage());
        } catch (\Throwable $ex) {
            return TestConnectionResult::error($ex->getMessage());
        }

        if (!is_scalar($result) || (string) $result === '') {
            return TestConnectionResult::error(__('Unknown error', 'wp-sms'));
        }

        $credit = (string) $result;
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
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
            $client   = new \SoapClient(self::WSDL, [
                'encoding'           => 'UTF-8',
                'exceptions'         => true,
                'connection_timeout' => 30,
            ]);
            $response = $client->$operation($params);
        } catch (\SoapFault $ex) {
            return DeliveryResult::failed($ex->getMessage());
        } catch (\Throwable $ex) {
            return DeliveryResult::failed($ex->getMessage());
        }

        return $onSuccess($response);
    }
}

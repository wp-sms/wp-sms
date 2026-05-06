<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Rayan SMS Panel — Iranian Payamak Panel reseller exposing a SOAP web service.
 *
 * SOAP endpoint: http://api.payamak-panel.com/post/send.asmx?wsdl
 *
 * Operations:
 *   SendSms({ username, password, from, to[], text, isflash, udh, recId, status })
 *     -> SendSmsResult (string/numeric message id, or error string)
 *   GetCredit({ username, password })
 *     -> GetCreditResult (numeric credit)
 *
 * Auth: account username + password sent in every SOAP request body.
 *
 * Out of scope: templates/patterns (the v7 source does not use the BaseServiceNumber
 * pattern endpoint that the same Payamak Panel platform offers), MMS, flash SMS,
 * status webhooks, inbound webhooks. Requires the PHP soap extension.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class RayanSmsPanelProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://api.payamak-panel.com/post/send.asmx?wsdl';

    public function getId(): string
    {
        return 'rayansmspanel';
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
                    'description' => __('Your Rayan SMS Panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Rayan SMS Panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the Rayan SMS Panel', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for the Rayan SMS Panel gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('Rayan SMS Panel credentials not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('Rayan SMS Panel sender not configured', 'wp-sms'));
        }

        $params = [
            'username' => $username,
            'password' => $password,
            'from'     => $sender,
            'to'       => [$message->getRecipient()],
            'text'     => $message->getBody(),
            'isflash'  => false,
            'udh'      => '',
            'recId'    => [0],
            'status'   => 0x0,
        ];

        return $this->callSoap('SendSms', $params, function ($response) {
            $result = $response->SendSmsResult ?? null;
            return $this->parseResponse($result, __('Rayan SMS Panel send failed', 'wp-sms'));
        });
    }

    private function parseResponse(mixed $result, string $defaultError): DeliveryResult
    {
        if ($result === null || $result === '') {
            return DeliveryResult::failed($defaultError);
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
            $response = $client->GetCredit(['username' => $username, 'password' => $password]);
        } catch (\Throwable $ex) {
            return null;
        }

        $credit = $response->GetCreditResult ?? null;
        return $credit !== null ? (string) $credit : null;
    }

    public function testConnection(): TestConnectionResult
    {
        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for the Rayan SMS Panel gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->GetCredit(['username' => $username, 'password' => $password]);
        } catch (\SoapFault $ex) {
            return TestConnectionResult::error($ex->getMessage());
        } catch (\Throwable $ex) {
            return TestConnectionResult::error($ex->getMessage());
        }

        $credit = $response->GetCreditResult ?? null;
        if ($credit === null) {
            return TestConnectionResult::error(__('Unknown error', 'wp-sms'));
        }

        $credit = (string) $credit;
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }

    /**
     * Invoke a SOAP operation and translate any failure into a DeliveryResult.
     *
     * @param callable(object): DeliveryResult $onSuccess
     */
    private function callSoap(string $operation, array $params, callable $onSuccess): DeliveryResult
    {
        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->$operation($params);
        } catch (\SoapFault $ex) {
            return DeliveryResult::failed($ex->getMessage());
        } catch (\Throwable $ex) {
            return DeliveryResult::failed($ex->getMessage());
        }

        return $onSuccess($response);
    }
}

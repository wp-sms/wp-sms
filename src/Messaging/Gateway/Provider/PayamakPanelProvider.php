<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Payamak Panel — Iranian SMS panel exposing the legacy SOAP web service.
 *
 * SOAP endpoint: http://api.payamak-panel.com/post/send.asmx?WSDL
 *
 * Operations:
 *   SendSms({ username, password, from, to, text, isflash, udh, recId, status })
 *     -> SendSmsResult (numeric message id; negative values are panel error codes)
 *   GetCredit({ username, password })
 *     -> GetCreditResult (numeric credit)
 *
 * Auth: account username + password sent in every SOAP request body.
 *
 * Out of scope: templates/patterns (the legacy SOAP API does not expose them —
 * the modern REST sibling at rest.payamak-panel.com does, see HostIranProvider /
 * MeliPayamakProvider), MMS, status webhooks, inbound webhooks. Requires the
 * PHP soap extension.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class PayamakPanelProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://api.payamak-panel.com/post/send.asmx?WSDL';

    public function getId(): string
    {
        return 'payamakpanel';
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
                    'description' => __('Your Payamak Panel account username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Payamak Panel account password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the Payamak Panel account', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for the Payamak Panel gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('Payamak Panel credentials not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('Payamak Panel sender not configured', 'wp-sms'));
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
            return $this->parseResponse($result, __('Payamak Panel send failed', 'wp-sms'));
        });
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
            return TestConnectionResult::error(__('PHP SOAP extension is required for the Payamak Panel gateway', 'wp-sms'));
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

<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Afe — Iranian SMS panel exposing two SOAP endpoints.
 *
 * SOAP endpoints:
 *   Send:   http://www.afe.ir/WebService/V4/BoxService.asmx?WSDL
 *           SendMessage({ Username, Password, Number, Mobile[], Message, Type })
 *           -> SendMessageResult.string (numeric message id, or error string)
 *   Credit: http://www.afe.ir/WebService/webservice.asmx?WSDL
 *           GetRemainingCredit({ Username, Password })
 *           -> GetRemainingCreditResult (numeric credit)
 *
 * Auth: account username + password sent in every SOAP request body.
 *
 * Out of scope: templates/patterns (not exposed by this SOAP API), MMS,
 * status webhooks, inbound webhooks. Requires the PHP soap extension.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class AfeProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_WSDL   = 'http://www.afe.ir/WebService/V4/BoxService.asmx?WSDL';
    private const CREDIT_WSDL = 'http://www.afe.ir/WebService/webservice.asmx?WSDL';

    public function getId(): string
    {
        return 'afe';
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
                    'description' => __('Your Afe panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Afe panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the Afe panel', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for the Afe gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('Afe credentials not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('Afe sender not configured', 'wp-sms'));
        }

        $params = [
            'Username' => $username,
            'Password' => $password,
            'Number'   => $sender,
            'Mobile'   => [$message->getRecipient()],
            'Message'  => $message->getBody(),
            'Type'     => 1, // 1 = normal, 0 = flash
        ];

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client   = new \SoapClient(self::SEND_WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->SendMessage($params);
        } catch (\SoapFault $ex) {
            return DeliveryResult::failed($ex->getMessage());
        } catch (\Throwable $ex) {
            return DeliveryResult::failed($ex->getMessage());
        }

        return $this->parseResponse($response);
    }

    private function parseResponse(mixed $response): DeliveryResult
    {
        $result = $response->SendMessageResult->string ?? null;
        if ($result === null || $result === '') {
            return DeliveryResult::failed(__('Afe send failed', 'wp-sms'));
        }

        // The v7 source sometimes returns an array of strings (one per recipient).
        if (is_array($result)) {
            $result = $result[0] ?? '';
        }

        $value = is_scalar($result) ? (string) $result : wp_json_encode($result);
        if ($value === '' || $value === false) {
            return DeliveryResult::failed(__('Afe send failed', 'wp-sms'));
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
            $client = new \SoapClient(self::CREDIT_WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $result = $client->GetRemainingCredit(['Username' => $username, 'Password' => $password]);
        } catch (\Throwable $ex) {
            return null;
        }

        $credit = $result->GetRemainingCreditResult ?? null;
        return $credit !== null ? (string) $credit : null;
    }

    public function testConnection(): TestConnectionResult
    {
        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for the Afe gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::CREDIT_WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $result = $client->GetRemainingCredit(['Username' => $username, 'Password' => $password]);
        } catch (\SoapFault $ex) {
            return TestConnectionResult::error($ex->getMessage());
        } catch (\Throwable $ex) {
            return TestConnectionResult::error($ex->getMessage());
        }

        $credit = $result->GetRemainingCreditResult ?? null;
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

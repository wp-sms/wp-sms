<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * ParsGreen — Iranian SMS panel exposing two SOAP web services.
 *
 * SOAP endpoints:
 *   Send:   http://login.parsgreen.com/Api/SendSMS.asmx?WSDL
 *           SendGroupSMS({ signature, from, to[], text, isFlash, udh, success, retStr[] })
 *             -> SendGroupSMSResult (1 = success, anything else = failure)
 *   Credit: http://login.parsgreen.com/Api/ProfileService.asmx?WSDL
 *           GetCredit({ signature })
 *             -> GetCreditResult (numeric credit)
 *
 * Auth: account API "signature" string sent in every SOAP request body. Unlike
 * most Iranian gateways ParsGreen does not expose a separate password — the
 * v7 plugin maps its `username` field to `signature`.
 *
 * Out of scope: templates/patterns (not exposed by this SOAP API), MMS, flash SMS,
 * status webhooks, inbound webhooks. Requires the PHP soap extension.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class ParsGreenProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL_SEND   = 'http://login.parsgreen.com/Api/SendSMS.asmx?WSDL';
    private const WSDL_CREDIT = 'http://login.parsgreen.com/Api/ProfileService.asmx?WSDL';

    public function getId(): string
    {
        return 'parsgreen';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'signature' => [
                    'type'        => 'secret',
                    'label'       => __('API Signature', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your ParsGreen API signature key (issued in the panel under API > Signature)', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the ParsGreen panel', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for the ParsGreen gateway', 'wp-sms'));
        }

        $signature = (string) $this->getSharedConfig('signature', '');
        $sender    = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($signature === '') {
            return DeliveryResult::failed(__('ParsGreen API signature not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('ParsGreen sender not configured', 'wp-sms'));
        }

        $params = [
            'signature' => $signature,
            'from'      => $sender,
            'to'        => [$message->getRecipient()],
            'text'      => $message->getBody(),
            'isFlash'   => false,
            'udh'       => '',
            'success'   => 0x0,
            'retStr'    => [0],
        ];

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL_SEND, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = (array) $client->SendGroupSMS($params);
        } catch (\SoapFault $ex) {
            return DeliveryResult::failed($ex->getMessage());
        } catch (\Throwable $ex) {
            return DeliveryResult::failed($ex->getMessage());
        }

        return $this->parseResponse($response['SendGroupSMSResult'] ?? null);
    }

    private function parseResponse(mixed $result): DeliveryResult
    {
        if ((int) $result === 1) {
            // ParsGreen's SendGroupSMS returns just a success flag, not a per-message id.
            return DeliveryResult::sent('');
        }

        $value = is_scalar($result) ? (string) $result : wp_json_encode($result);
        return DeliveryResult::failed($value !== '' ? $value : __('ParsGreen send failed', 'wp-sms'));
    }

    public function getCredit(): ?string
    {
        if (!class_exists('SoapClient')) {
            return null;
        }

        $signature = (string) $this->getSharedConfig('signature', '');
        if ($signature === '') {
            return null;
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL_CREDIT, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = (array) $client->GetCredit(['signature' => $signature]);
        } catch (\Throwable $ex) {
            return null;
        }

        $credit = $response['GetCreditResult'] ?? null;
        return $credit !== null ? (string) $credit : null;
    }

    public function testConnection(): TestConnectionResult
    {
        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for the ParsGreen gateway', 'wp-sms'));
        }

        $signature = (string) $this->getSharedConfig('signature', '');
        if ($signature === '') {
            return TestConnectionResult::error(__('API Signature is required', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL_CREDIT, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = (array) $client->GetCredit(['signature' => $signature]);
        } catch (\SoapFault $ex) {
            return TestConnectionResult::error($ex->getMessage());
        } catch (\Throwable $ex) {
            return TestConnectionResult::error($ex->getMessage());
        }

        $credit = $response['GetCreditResult'] ?? null;
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

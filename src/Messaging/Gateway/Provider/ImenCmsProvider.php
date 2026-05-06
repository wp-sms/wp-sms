<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * ImenCMS — Iranian SMS panel exposing a SOAP web service.
 *
 * SOAP endpoint: http://www.imencms.ir/SMS/sms.asmx?WSDL
 *
 * Operations:
 *   SendSMS({ MobileNo, SMSText, AcountID, LineNo })
 *     -> Send_x0020_One_x0020_SMSResult (numeric message id, or error string)
 *   GetCredit({ AcountID })
 *     -> GetCreditResult (numeric credit)
 *
 * Auth: a single account id token sent as `AcountID` (legacy spelling, kept
 * verbatim from the WSDL). The username field on the gateway form is unused
 * by the wire protocol — only the account id (mapped to the password slot)
 * is actually transmitted.
 *
 * Out of scope: templates/patterns (not exposed by this SOAP API), MMS,
 * status webhooks, inbound webhooks. Requires the PHP soap extension.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class ImenCmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://www.imencms.ir/SMS/sms.asmx?WSDL';

    public function getId(): string
    {
        return 'imencms';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'account_id' => [
                    'type'        => 'secret',
                    'label'       => __('Account ID', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your ImenCMS account id (provided by the panel)', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the ImenCMS panel', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for the ImenCMS gateway', 'wp-sms'));
        }

        $accountId = (string) $this->getSharedConfig('account_id', '');
        $sender    = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($accountId === '') {
            return DeliveryResult::failed(__('ImenCMS Account ID not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('ImenCMS sender not configured', 'wp-sms'));
        }

        $params = [
            'MobileNo' => [$message->getRecipient()],
            'SMSText'  => $message->getBody(),
            'AcountID' => $accountId,
            'LineNo'   => $sender,
        ];

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client   = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->SendSMS($params);
        } catch (\SoapFault $ex) {
            return DeliveryResult::failed($ex->getMessage());
        } catch (\Throwable $ex) {
            return DeliveryResult::failed($ex->getMessage());
        }

        $result = $response->Send_x0020_One_x0020_SMSResult ?? null;
        return $this->parseResponse($result);
    }

    private function parseResponse(mixed $result): DeliveryResult
    {
        if ($result === null || $result === '') {
            return DeliveryResult::failed(__('ImenCMS send failed', 'wp-sms'));
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

        $accountId = (string) $this->getSharedConfig('account_id', '');
        if ($accountId === '') {
            return null;
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client   = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->GetCredit(['AcountID' => $accountId]);
        } catch (\Throwable $ex) {
            return null;
        }

        $credit = $response->GetCreditResult ?? null;
        return $credit !== null ? (string) $credit : null;
    }

    public function testConnection(): TestConnectionResult
    {
        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for the ImenCMS gateway', 'wp-sms'));
        }

        $accountId = (string) $this->getSharedConfig('account_id', '');
        if ($accountId === '') {
            return TestConnectionResult::error(__('Account ID is required', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client   = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->GetCredit(['AcountID' => $accountId]);
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
}

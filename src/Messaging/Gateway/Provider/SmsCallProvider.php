<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * SMSCall — Iranian SMS gateway exposing a SOAP webservice at smscall.ir.
 *
 *   WSDL:    http://webservice.smscall.ir/index.php?wsdl
 *   Send:    Send_Group_SMS(username, password, to, text, from, isFlash)
 *   Credit:  CREDIT_LINESMS(username, password, from)
 *
 * Auth: account username + password sent as SOAP arguments.
 * Response: scalar message id / credit count returned directly.
 *
 * Out of scope (not exposed by the API): MMS, delivery webhooks, inbound
 * webhooks, template/pattern messaging, and opt-out detection. Flash SMS is
 * supported by the underlying API but is not wired into the v8 send path.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class SmsCallProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://webservice.smscall.ir/index.php?wsdl';

    public function getId(): string
    {
        return 'smscall';
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
                    'description' => __('Your SMSCall panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your SMSCall panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the SMSCall panel', 'wp-sms'),
                        'placeholder' => '3000xxxxxxxx',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('SMSCall credentials not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('SMSCall sender not configured', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for SMSCall', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL);

            $result = $client->Send_Group_SMS(
                $username,
                $password,
                $message->getRecipient(),
                $message->getBody(),
                $sender,
                1,
            );

            if ($result === null || $result === false || $result === '') {
                return DeliveryResult::failed(__('SMSCall send failed', 'wp-sms'));
            }

            return DeliveryResult::sent((string) (is_scalar($result) ? $result : ''));
        } catch (\SoapFault $e) {
            return DeliveryResult::failed($e->faultstring ?: $e->getMessage());
        } catch (\Throwable $e) {
            return DeliveryResult::failed($e->getMessage());
        }
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        if ($username === '' || $password === '' || !class_exists('SoapClient')) {
            return null;
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL);
            $result = $client->CREDIT_LINESMS($username, $password, $sender);
            return $result !== null && $result !== false ? (string) $result : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for SMSCall', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL);
            $result = $client->CREDIT_LINESMS($username, $password, $sender);

            if ($result === null || $result === false) {
                return TestConnectionResult::error(__('Unexpected response from SMSCall', 'wp-sms'));
            }

            $credit = (string) $result;
            return TestConnectionResult::ok(
                sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
                ['credit' => $credit],
            );
        } catch (\SoapFault $e) {
            return TestConnectionResult::error($e->faultstring ?: $e->getMessage());
        } catch (\Throwable $e) {
            return TestConnectionResult::error($e->getMessage());
        }
    }
}

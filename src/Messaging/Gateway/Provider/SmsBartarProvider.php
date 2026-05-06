<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * SMS-Bartar — Iranian SMS gateway exposing a SOAP webservice at sms-bartar.com.
 *
 *   WSDL:    http://sms.sms-bartar.com/webservice/?WSDL
 *   Send:    sendToMany(to[], text, from)
 *   Credit:  accountInfo() -> { remaining }
 *
 * Auth: account credentials supplied as SoapClient login/password options
 * (HTTP basic over SOAP). Response is the message-id payload from sendToMany.
 *
 * Out of scope (not exposed by the API): MMS, flash SMS, delivery webhooks,
 * inbound webhooks, template/pattern messaging, and opt-out detection.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class SmsBartarProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://sms.sms-bartar.com/webservice/?WSDL';

    public function getId(): string
    {
        return 'smsbartar';
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
                    'description' => __('Your SMS-Bartar panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your SMS-Bartar panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the SMS-Bartar panel', 'wp-sms'),
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
            return DeliveryResult::failed(__('SMS-Bartar credentials not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('SMS-Bartar sender not configured', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for SMS-Bartar', 'wp-sms'));
        }

        try {
            $client = $this->buildClient($username, $password);
            $result = $client->sendToMany([$message->getRecipient()], $message->getBody(), $sender);

            if ($result === null || $result === false || $result === '') {
                return DeliveryResult::failed(__('SMS-Bartar send failed', 'wp-sms'));
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

        try {
            $client = $this->buildClient($username, $password);
            $info = $client->accountInfo();
            $remaining = $info->remaining ?? null;
            return $remaining !== null ? (string) $remaining : null;
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
            return TestConnectionResult::error(__('PHP SOAP extension is required for SMS-Bartar', 'wp-sms'));
        }

        try {
            $client = $this->buildClient($username, $password);
            $info = $client->accountInfo();
            $remaining = $info->remaining ?? null;

            if ($remaining === null) {
                return TestConnectionResult::error(__('Unexpected response from SMS-Bartar', 'wp-sms'));
            }

            $credit = (string) $remaining;
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

    private function buildClient(string $username, string $password): \SoapClient
    {
        @ini_set('soap.wsdl_cache_enabled', '0');
        return new \SoapClient(self::WSDL, [
            'login'    => $username,
            'password' => $password,
        ]);
    }
}

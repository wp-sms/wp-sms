<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * TSMS — Iranian SMS panel exposing a SOAP web service with positional arguments.
 *
 * SOAP endpoint: http://www.tsms.ir/soapWSDL/?wsdl
 *
 * Operations (note: arguments are POSITIONAL, not associative):
 *   sendSms(username, password, fromArr, toArr, msgArr, mclass, messageId)
 *     -> mixed (typically a numeric message id)
 *   userinfo(username, password)
 *     -> array; first element exposes ->credit
 *
 * Auth: account username + password supplied as the first two SOAP arguments.
 *
 * Out of scope: templates/patterns (not exposed by this SOAP API), MMS, flash SMS,
 * status webhooks, inbound webhooks. Requires the PHP soap extension.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class TsmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://www.tsms.ir/soapWSDL/?wsdl';

    public function getId(): string
    {
        return 'tsms';
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
                    'description' => __('Your TSMS panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your TSMS panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the TSMS panel', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for the TSMS gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('TSMS credentials not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('TSMS sender not configured', 'wp-sms'));
        }

        $messageId = (string) wp_rand();

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $result = $client->sendSms(
                $username,
                $password,
                [$sender],
                [$message->getRecipient()],
                [$message->getBody()],
                [''],
                $messageId,
            );
        } catch (\SoapFault $ex) {
            return DeliveryResult::failed($ex->getMessage());
        } catch (\Throwable $ex) {
            return DeliveryResult::failed($ex->getMessage());
        }

        return $this->parseSendResponse($result, $messageId);
    }

    private function parseSendResponse(mixed $result, string $fallbackId): DeliveryResult
    {
        if ($result === null || $result === '') {
            return DeliveryResult::failed(__('TSMS send failed', 'wp-sms'));
        }

        // sendSms can return an array, an object, or a scalar — normalise to a string id when possible.
        if (is_array($result)) {
            $first = reset($result);
            if (is_scalar($first)) {
                return DeliveryResult::sent((string) $first);
            }
            // No usable scalar id — fall back to the random id we sent in.
            return DeliveryResult::sent($fallbackId);
        }

        if (is_object($result)) {
            return DeliveryResult::sent($fallbackId);
        }

        $value = (string) $result;
        if (is_numeric($value)) {
            return DeliveryResult::sent($value);
        }

        // Non-numeric scalar typically signals an error.
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
            $result = $client->userinfo($username, $password);
        } catch (\Throwable $ex) {
            return null;
        }

        if (is_array($result) && isset($result[0]->credit)) {
            return (string) $result[0]->credit;
        }
        if (is_object($result) && isset($result->credit)) {
            return (string) $result->credit;
        }

        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for the TSMS gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $result = $client->userinfo($username, $password);
        } catch (\SoapFault $ex) {
            return TestConnectionResult::error($ex->getMessage());
        } catch (\Throwable $ex) {
            return TestConnectionResult::error($ex->getMessage());
        }

        $credit = null;
        if (is_array($result) && isset($result[0]->credit)) {
            $credit = (string) $result[0]->credit;
        } elseif (is_object($result) && isset($result->credit)) {
            $credit = (string) $result->credit;
        }

        if ($credit === null) {
            return TestConnectionResult::error(__('Unknown error', 'wp-sms'));
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }
}

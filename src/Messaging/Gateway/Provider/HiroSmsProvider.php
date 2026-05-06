<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Hiro SMS — Iranian SMS panel exposing a SOAP web service.
 *
 * SOAP endpoint: http://my.hiro-sms.com/webservice/send.php?wsdl
 *
 * Operations:
 *   SendMultiSMS(from[], to[], message[], type[], username, password)
 *     -> array of message ids / status codes
 *   GetCredit(username, password, ["", ""])
 *     -> numeric credit
 *
 * Auth: account username + password sent as positional SOAP arguments.
 *
 * Out of scope: templates/patterns (not exposed), MMS, status webhooks, inbound
 * webhooks. Requires the PHP soap extension.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class HiroSmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'http://my.hiro-sms.com/webservice/send.php?wsdl';

    public function getId(): string
    {
        return 'hirosms';
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
                    'description' => __('Your Hiro SMS panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Hiro SMS panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the Hiro SMS panel', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!class_exists('SoapClient')) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for the Hiro SMS gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('Hiro SMS credentials not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('Hiro SMS sender not configured', 'wp-sms'));
        }

        $to       = [$message->getRecipient()];
        $from     = [$sender];
        $messages = [$message->getBody()];
        $types    = [false];

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->SendMultiSMS($from, $to, $messages, $types, $username, $password);
        } catch (\Throwable $ex) {
            return DeliveryResult::failed($ex->getMessage());
        }

        return $this->parseSendResponse($response);
    }

    private function parseSendResponse(mixed $response): DeliveryResult
    {
        // Typical SOAP responses: array of ids, single id string, or error code string.
        if (is_array($response) || is_object($response)) {
            $values = is_array($response) ? array_values($response) : (array) $response;
            $first  = $values[0] ?? null;
            if (is_scalar($first) && is_numeric($first) && (int) $first > 0) {
                return DeliveryResult::sent((string) $first);
            }
            return DeliveryResult::failed((string) wp_json_encode($response));
        }

        if (is_scalar($response) && is_numeric($response) && (int) $response > 0) {
            return DeliveryResult::sent((string) $response);
        }

        return DeliveryResult::failed((string) ($response ?? __('Hiro SMS send failed', 'wp-sms')));
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
            $result = $client->GetCredit($username, $password, ['', '']);
        } catch (\Throwable $ex) {
            return null;
        }

        if (!is_scalar($result) || !is_numeric($result)) {
            return null;
        }

        return (string) round((float) $result);
    }

    public function testConnection(): TestConnectionResult
    {
        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for the Hiro SMS gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $result = $client->GetCredit($username, $password, ['', '']);
        } catch (\Throwable $ex) {
            return TestConnectionResult::error($ex->getMessage());
        }

        if (!is_scalar($result) || !is_numeric($result)) {
            return TestConnectionResult::error(__('Unknown error', 'wp-sms'));
        }

        $credit = (string) round((float) $result);
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }
}

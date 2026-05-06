<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * ADP Digital — Iranian SMS gateway exposing a flat HTTP/GET REST API.
 *
 * Endpoints (all GET, plain-text response):
 *   Send:    GET http://ws.adpdigital.com/url/send
 *            ?username=&password=&dstaddress=&body=&clientid=&type=text&unicode=1
 *   Credit:  GET http://ws.adpdigital.com/url/balance
 *            ?username=&password=&facility=send
 *
 * Auth: account username + password as query-string parameters.
 *
 * Response shape: opaque text. Strings containing the substring "ERR" indicate
 * an error; otherwise the numeric prefix of the response is treated as the
 * provider message id (or, for /balance, the remaining credit).
 *
 * The v7 path normalises Iranian numbers by replacing leading "09" with "989",
 * which is preserved here so existing user inputs keep working.
 *
 * Out of scope: templates/patterns (not exposed by the API), MMS, status
 * webhooks, inbound webhooks.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class AdpDigitalProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'http://ws.adpdigital.com/url';

    public function getId(): string
    {
        return 'adpdigital';
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
                    'description' => __('Your ADP Digital panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your ADP Digital panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line (clientid) provided by ADP Digital', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('ADP Digital credentials not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('ADP Digital sender not configured', 'wp-sms'));
        }

        // v7 quirk preserved: normalise Iranian "09xxxxxxxxx" to "989xxxxxxxxx".
        $recipient = preg_replace('/^09/', '989', $message->getRecipient());

        $url = self::API_BASE . '/send?' . http_build_query([
            'username'   => $username,
            'password'   => $password,
            'dstaddress' => $recipient,
            'body'       => $message->getBody(),
            'clientid'   => $sender,
            'type'       => 'text',
            'unicode'    => '1',
        ]);

        $result = $this->httpGet($url);

        return $this->parseResponse($result, __('ADP Digital send failed', 'wp-sms'));
    }

    private function parseResponse(array|DeliveryResult $result, string $defaultError): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $body = (string) $result['body'];

        if ($body === '' || stripos($body, 'ERR') !== false) {
            return DeliveryResult::failed($body !== '' ? $body : $defaultError);
        }

        $providerId = preg_replace('/[^0-9]/', '', $body);
        return DeliveryResult::sent((string) $providerId);
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        if ($username === '' || $password === '') {
            return null;
        }

        $url = self::API_BASE . '/balance?' . http_build_query([
            'username' => $username,
            'password' => $password,
            'facility' => 'send',
        ]);

        $result = $this->httpGet($url);
        if ($result instanceof DeliveryResult) {
            return null;
        }

        $body = (string) $result['body'];
        if ($body === '' || stripos($body, 'ERR') !== false) {
            return null;
        }

        return preg_replace('/[^0-9]/', '', $body);
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        $url = self::API_BASE . '/balance?' . http_build_query([
            'username' => $username,
            'password' => $password,
            'facility' => 'send',
        ]);

        $result = $this->httpGet($url);

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the ADP Digital API. Check your server\'s internet connection.', 'wp-sms'),
            );
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from ADP Digital (HTTP %d)', 'wp-sms'), $result['code']),
            );
        }

        $body = (string) $result['body'];
        if ($body === '' || stripos($body, 'ERR') !== false) {
            return TestConnectionResult::error($body !== '' ? $body : __('Unknown error', 'wp-sms'));
        }

        $credit = preg_replace('/[^0-9]/', '', $body);
        $credit = $credit !== '' ? $credit : 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }
}

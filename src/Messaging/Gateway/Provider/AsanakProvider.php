<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Asanak — Iranian SMS gateway with a flat HTTP/GET REST API.
 *
 * Endpoint:
 *   Send: GET http://panel.asanak.ir/webservice/v1rest/sendsms
 *         ?username=&password=&source=&destination=&message=
 *
 * Auth: account username + password as query-string parameters.
 *
 * Response: opaque text. The v7 path forwards multiple recipients as a
 * "-" delimited list; we send a single recipient per call here.
 *
 * Asanak's REST API does not expose a credit endpoint — getCredit() therefore
 * returns null and testConnection() validates only that credentials are set.
 *
 * Out of scope: templates/patterns, MMS, status webhooks, inbound webhooks.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class AsanakProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_URL = 'http://panel.asanak.ir/webservice/v1rest/sendsms';

    public function getId(): string
    {
        return 'asanak';
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
                    'description' => __('Your Asanak panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Asanak panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the Asanak panel', 'wp-sms'),
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
            return DeliveryResult::failed(__('Asanak credentials not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('Asanak sender not configured', 'wp-sms'));
        }

        $url = self::SEND_URL . '?' . http_build_query([
            'username'    => $username,
            'password'    => $password,
            'source'      => $sender,
            'destination' => $message->getRecipient(),
            'message'     => trim($message->getBody()),
        ]);

        $result = $this->httpGet($url, [
            'headers' => [
                'Accept'       => 'text/html',
                'Connection'   => 'Keep-Alive',
                'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
            ],
        ]);

        return $this->parseResponse($result);
    }

    private function parseResponse(array|DeliveryResult $result): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(
                sprintf(__('Asanak send failed (HTTP %d)', 'wp-sms'), $result['code']),
            );
        }

        $body = trim((string) $result['body']);
        if ($body === '') {
            return DeliveryResult::failed(__('Empty response from Asanak', 'wp-sms'));
        }

        // Numeric responses indicate a message id; everything else is treated as an error string.
        if (is_numeric($body)) {
            return DeliveryResult::sent($body);
        }

        // Some responses are comma/space separated lists of message ids — accept those too.
        $candidate = preg_replace('/[\s,;]+/', '', $body);
        if ($candidate !== '' && is_numeric($candidate)) {
            return DeliveryResult::sent($candidate);
        }

        return DeliveryResult::failed($body);
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        return TestConnectionResult::ok(
            __('Credentials saved — Asanak does not expose a credit/balance endpoint to verify online.', 'wp-sms'),
        );
    }
}

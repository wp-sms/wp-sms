<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * iSMS.ir — Iranian SMS gateway with a POST/form REST endpoint.
 *
 * Endpoint:
 *   Send: POST http://ws3584.isms.ir/sendWS
 *         { username, password, mobiles[], body }
 *
 * Auth: account username + password sent in the request body.
 *
 * Response: JSON. v7 simply forwards the decoded JSON; here we treat any
 * decoded response as success and use a numeric "id" or "messageId" field
 * if present.
 *
 * The legacy v7 schema includes an "API key" toggle but the SendSMS path
 * does not actually use it, so this provider exposes only username/password.
 *
 * Out of scope: templates/patterns (not exposed), MMS, status webhooks,
 * inbound webhooks. iSMS.ir does not expose a credit endpoint via this
 * service URL, so getCredit() returns null.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class IsmsieProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_URL = 'http://ws3584.isms.ir/sendWS';

    public function getId(): string
    {
        return 'ismsie';
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
                    'description' => __('Your iSMS.ir panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your iSMS.ir panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Optional dedicated line purchased from the iSMS.ir panel', 'wp-sms'),
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
            return DeliveryResult::failed(__('iSMS.ir credentials not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::SEND_URL, [
            'body' => [
                'username' => $username,
                'password' => $password,
                'mobiles'  => [$message->getRecipient()],
                'body'     => $message->getBody(),
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
                sprintf(__('iSMS.ir send failed (HTTP %d)', 'wp-sms'), $result['code']),
            );
        }

        $body = (string) $result['body'];
        if ($body === '') {
            return DeliveryResult::failed(__('Empty response from iSMS.ir', 'wp-sms'));
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            // Non-JSON response — treat as opaque success token if present.
            return DeliveryResult::sent($body);
        }

        // Common error shapes — providers vary, so look for an explicit error flag/message.
        if (isset($data['error']) && $data['error']) {
            $msg = is_string($data['error']) ? $data['error'] : (string) ($data['message'] ?? __('iSMS.ir send failed', 'wp-sms'));
            return DeliveryResult::failed($msg);
        }
        if (isset($data['status']) && in_array($data['status'], ['error', 'failed', false, 0], true)) {
            return DeliveryResult::failed((string) ($data['message'] ?? __('iSMS.ir send failed', 'wp-sms')));
        }

        $providerId = $data['messageId']
            ?? $data['message_id']
            ?? $data['id']
            ?? (isset($data[0]) && is_scalar($data[0]) ? $data[0] : null);

        return DeliveryResult::sent($providerId !== null ? (string) $providerId : null);
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        return TestConnectionResult::ok(
            __('Credentials saved — iSMS.ir does not expose a credit/balance endpoint to verify online.', 'wp-sms'),
        );
    }
}

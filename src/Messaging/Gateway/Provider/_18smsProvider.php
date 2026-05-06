<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * 18sms — Iranian SMS gateway exposing a JSON-over-HTTP REST API.
 *
 *   Send:    GET http://18sms.ir/webservice/rest/sms_send
 *              ?login_username=…&login_password=…
 *              &receiver_number=…&note_arr[]=…&sender_number=…
 *   Credit:  GET http://18sms.ir/webservice/rest/user_info
 *              ?login_username=…&login_password=…
 *              → { result: ":true", list: { cash: <int> } } on success
 *              → { status: "ERR", error_string: "<message>" } on failure
 *
 * Auth: panel username + password sent as query parameters on every request.
 * Response shape: JSON object. Errors surface as either `{status:"ERR",
 * error_string:…}` (transport/auth errors) or `{result:":false", error:…}`
 * (business-logic errors from /user_info).
 *
 * Out of scope: the gateway publishes no template, status-callback, or
 * inbound-MO endpoints. SupportsTemplates is intentionally not implemented.
 *
 * v7 → v8 reconciliation: v7 declared `has_key = true`, exposing an "API Key"
 * config field that was never read by SendSMS or GetCredit — pure dead config.
 * v8 keeps the field as an optional shared input so existing installs don't
 * appear to lose configuration during migration, but it is not transmitted.
 * v7 returned the raw JSON object as the success payload; v8 wraps that in a
 * DeliveryResult and uses the JSON `request_id`/`id` field (when present) as
 * the provider message ID.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class _18smsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'http://18sms.ir/webservice/rest';

    public function getId(): string
    {
        return '18sms';
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
                    'description' => __('Your 18sms panel username.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your 18sms panel password.', 'wp-sms'),
                ],
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Optional API key from your 18sms panel. Not currently used by the send/credit endpoints.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from your 18sms panel.', 'wp-sms'),
                        'placeholder' => '9810000000',
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
            return DeliveryResult::failed(__('18sms credentials not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('18sms sender not configured', 'wp-sms'));
        }

        $url = add_query_arg([
            'login_username'  => $username,
            'login_password'  => $password,
            'receiver_number' => $message->getRecipient(),
            'note_arr[]'      => $message->getBody(),
            'sender_number'   => $sender,
        ], self::API_BASE . '/sms_send');

        $result = $this->httpGet($url);

        return $this->parseResponse($result);
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        if ($username === '' || $password === '') {
            return null;
        }

        $url = add_query_arg([
            'login_username' => $username,
            'login_password' => $password,
        ], self::API_BASE . '/user_info');

        $result = $this->httpGet($url);
        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return null;
        }

        if (($data['status'] ?? null) === 'ERR') {
            return null;
        }
        if (($data['result'] ?? '') !== ':true') {
            return null;
        }

        $cash = $data['list']['cash'] ?? null;
        return $cash !== null ? (string) $cash : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        $url = add_query_arg([
            'login_username' => $username,
            'login_password' => $password,
        ], self::API_BASE . '/user_info');

        $result = $this->httpGet($url);
        $data   = $this->validateTestResponse($result, '18sms');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (($data['status'] ?? null) === 'ERR') {
            return TestConnectionResult::error((string) ($data['error_string'] ?? __('18sms rejected the request', 'wp-sms')));
        }
        if (($data['result'] ?? '') !== ':true') {
            return TestConnectionResult::error((string) ($data['error'] ?? __('18sms rejected the request', 'wp-sms')));
        }

        $credit = isset($data['list']['cash']) ? (string) $data['list']['cash'] : 'N/A';
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }

    private function parseResponse(array|DeliveryResult $result): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(__('Invalid response from 18sms', 'wp-sms'));
        }

        if (($data['status'] ?? null) === 'ERR') {
            return DeliveryResult::failed((string) ($data['error_string'] ?? __('18sms send failed', 'wp-sms')));
        }

        // Some 18sms endpoints surface logical failures via result/error rather
        // than the transport-level status flag.
        if (isset($data['result']) && $data['result'] !== ':true' && !empty($data['error'])) {
            return DeliveryResult::failed((string) $data['error']);
        }

        // The send response shape isn't fully pinned by docs; surface any
        // candidate id field but don't require one.
        $providerId = (string) (
            $data['request_id']
            ?? $data['id']
            ?? $data['list']['id']
            ?? ''
        );

        return DeliveryResult::sent($providerId);
    }
}

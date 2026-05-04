<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * HostPinnacle — Kenyan SMS aggregator (HostPinnacle Cloud Limited).
 *
 * Auth: apiKey header + userid/password form fields. Send and balance share
 * the same base. WhatsApp lives on a separate API and is not handled here.
 */
// TODO(status-callback): provider exposes POST /SMSApi/webhook/create and
// can deliver DLR via 'smswebhook', but the callback payload schema is
// undocumented and there is no signing scheme — defer SupportsStatusCallback
// until a live sample is captured and a configurable URL token is wired.
//
// TODO(whatsapp): provider has a separate WhatsApp API at
// https://hostpinnacle.co.ke/whatsapp/api/send (instance ID + access token).
// Out of scope for this provider — would warrant a sibling provider class.
class HostPinnacleProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = true;

    private const API_BASE = 'https://smsportal.hostpinnacle.co.ke/SMSApi';

    public function getId(): string
    {
        return 'hostpinnacle';
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
                    'label'       => __('Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your registered HostPinnacle portal username.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your HostPinnacle portal password.', 'wp-sms'),
                ],
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Optional. API key from your HostPinnacle SMS portal (Account → API). The portal accepts either Username + Password or an API key — provide whichever is easier; supplying the API key sends it as an additional auth header.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'WSMS',
                        'description' => __('Approved alphanumeric Sender ID registered with HostPinnacle. Pre-approval is required.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $apiKey   = (string) $this->getSharedConfig('api_key', '');
        $senderId = (string) $this->getChannelConfig('sms', 'sender_id', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('HostPinnacle credentials not configured', 'wp-sms'));
        }

        $body = $message->getBody();

        // v7 used isset($options['send_unicode']) ? 'text' : 'unicode' — the
        // logic is inverted (a flag named "send_unicode" ought to *enable*
        // unicode, not disable it). Autodetect from body bytes instead.
        $msgType = $this->isAscii($body) ? 'text' : 'unicode';

        $args = [
            'body' => [
                'userid'     => $username,
                'password'   => $password,
                'sendMethod' => 'quick',
                'mobile'     => ltrim($message->getRecipient(), '+'),
                'msg'        => $body,
                'senderid'   => $senderId,
                'msgType'    => $msgType,
                'output'     => 'json',
            ],
        ];
        if ($apiKey !== '') {
            $args['headers'] = ['apiKey' => $apiKey];
        }

        $result = $this->httpPost(self::API_BASE . '/send', $args);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid HostPinnacle credentials', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(
                sprintf(__('Invalid response from HostPinnacle (HTTP %d)', 'wp-sms'), $result['code'])
            );
        }

        if (($data['status'] ?? '') === 'error') {
            return DeliveryResult::failed(
                $data['reason'] ?? __('HostPinnacle rejected the message', 'wp-sms'),
                meta: array_filter([
                    'hostpinnacle_status_code' => $data['statusCode'] ?? null,
                ]),
            );
        }

        return DeliveryResult::queued((string) ($data['transactionId'] ?? ''));
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return null;
        }

        $result = $this->httpPost(self::API_BASE . '/account/readstatus', [
            'body' => [
                'userid'   => $username,
                'password' => $password,
                'output'   => 'json',
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return null;
        }

        if (($data['response']['code'] ?? null) !== '200') {
            return null;
        }

        $balance = $data['response']['account']['smsBalance'] ?? null;
        return $balance === null ? null : (string) $balance;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('Username and Password are required', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/account/readstatus', [
            'body' => [
                'userid'   => $username,
                'password' => $password,
                'output'   => 'json',
            ],
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid HostPinnacle credentials', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'HostPinnacle');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (($data['response']['code'] ?? null) !== '200') {
            return TestConnectionResult::error(
                $data['response']['msg'] ?? __('HostPinnacle rejected the credentials', 'wp-sms')
            );
        }

        $balance = (string) ($data['response']['account']['smsBalance'] ?? '');

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s', 'wp-sms'), $balance),
            ['balance' => $balance],
        );
    }

    private function isAscii(string $value): bool
    {
        return $value === '' || mb_check_encoding($value, 'ASCII');
    }
}

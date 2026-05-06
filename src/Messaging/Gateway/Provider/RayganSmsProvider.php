<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * RayganSMS — Iranian SMS gateway, REST/JSON API hosted at smspanel.trez.ir.
 *
 *   Send:    POST http://smspanel.trez.ir/api/smsAPI/SendMessage
 *            body: { PhoneNumber, Message, Mobiles, UserGroupID, SendDateInTimeStamp }
 *   Credit:  POST http://smspanel.trez.ir/api/smsAPI/GetCredit
 *
 * Auth: HTTP Basic (Authorization: Basic base64(username:password)).
 * Response shape: { Code, Message, Result } — Code "0" indicates success.
 *
 * Out of scope (not exposed by the API): MMS, flash SMS, delivery webhooks,
 * inbound webhooks, template/pattern messaging, and opt-out detection.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class RayganSmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'http://smspanel.trez.ir/api/smsAPI';

    public function getId(): string
    {
        return 'raygansms';
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
                    'description' => __('Your RayganSMS panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your RayganSMS panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the RayganSMS panel', 'wp-sms'),
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
            return DeliveryResult::failed(__('RayganSMS credentials not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('RayganSMS sender not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/SendMessage', [
            'headers' => $this->authHeaders($username, $password),
            'body'    => wp_json_encode([
                'PhoneNumber'         => $sender,
                'Message'             => $message->getBody(),
                'Mobiles'             => [$message->getRecipient()],
                'UserGroupID'         => uniqid(),
                'SendDateInTimeStamp' => time(),
            ]),
        ]);

        return $this->parseResponse($result);
    }

    private function parseResponse(array|DeliveryResult $result): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(__('Invalid response from RayganSMS', 'wp-sms'));
        }

        if ((string) ($data['Code'] ?? '') === '0') {
            $providerId = $data['Result'] ?? null;
            return DeliveryResult::sent($providerId !== null ? (string) $providerId : null);
        }

        return DeliveryResult::failed((string) ($data['Message'] ?? __('RayganSMS send failed', 'wp-sms')));
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        if ($username === '' || $password === '') {
            return null;
        }

        $result = $this->httpPost(self::API_BASE . '/GetCredit', [
            'headers' => $this->authHeaders($username, $password),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || (string) ($data['Code'] ?? '') !== '0') {
            return null;
        }

        return (string) ($data['Result'] ?? '');
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/GetCredit', [
            'headers' => $this->authHeaders($username, $password),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid credentials', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'RayganSMS');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if ((string) ($data['Code'] ?? '') !== '0') {
            $message = (string) ($data['Message'] ?? __('Unknown error', 'wp-sms'));
            return TestConnectionResult::error($message);
        }

        $credit = (string) ($data['Result'] ?? 'N/A');
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }

    private function authHeaders(string $username, string $password): array
    {
        return [
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
        ];
    }
}

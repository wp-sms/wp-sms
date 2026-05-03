<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Comilio — Italian SMS gateway (https://comilio.it/), operated by Pro Solid s.r.l.
 *
 * Auth: HTTP Basic with the account email and password.
 *
 * Send:    POST https://api.comilio.it/rest/v1/message
 *          (JSON body: message_type, phone_numbers[], text, sender_string?)
 *          Response 201 with top-level `message_id` on success; `error` on failure.
 * Credits: GET  https://api.comilio.it/rest/v1/credits
 *          Returns an array of {message_type, quantity} buckets, one per tier
 *          plus a separate "International" bucket.
 *
 * Three message-type tiers: Classic (cheapest, no custom sender, no DLR),
 * Smart (custom sender), SmartPro (custom sender + DLR + immediate delivery).
 *
 * DLR is poll-only via GET /message/{id} — no webhook is documented, so this
 * provider does not implement SupportsStatusCallback.
 *
 * International destinations deduct 2 credits per SMS instead of 1; the
 * "International" credits bucket is not surfaced separately by getCredit().
 */
class ComilioProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE             = 'https://api.comilio.it/rest/v1';
    private const DEFAULT_MESSAGE_TYPE = 'SmartPro';

    /** @var string[] */
    private const MESSAGE_TYPES = ['Classic', 'Smart', 'SmartPro'];

    public function getId(): string
    {
        return 'comilio';
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
                    'label'       => __('Username (email)', 'wp-sms'),
                    'required'    => true,
                    'description' => __('The email address you use to log in to the Comilio control panel.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Comilio account password.', 'wp-sms'),
                ],
                'message_type' => [
                    'type'        => 'select',
                    'label'       => __('Message Type', 'wp-sms'),
                    'required'    => true,
                    'default'     => self::DEFAULT_MESSAGE_TYPE,
                    'description' => __('SmartPro guarantees immediate delivery and supports custom senders and delivery receipts. Smart supports custom senders. Classic is the cheapest tier but ignores the sender field and does not provide DLR.', 'wp-sms'),
                    'options'     => [
                        ['value' => 'Classic',  'label' => __('Classic — cheapest, no custom sender, no DLR', 'wp-sms')],
                        ['value' => 'Smart',    'label' => __('Smart — custom sender', 'wp-sms')],
                        ['value' => 'SmartPro', 'label' => __('SmartPro — custom sender, DLR, immediate delivery', 'wp-sms')],
                    ],
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender', 'wp-sms'),
                        'required'    => false,
                        'placeholder' => 'MyBrand',
                        'description' => __('Optional alphanumeric sender ID, max 11 characters. Ignored on the Classic tier.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'unicode'         => true,
            'test_connection' => true,
        ]);
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username    = (string) $this->getSharedConfig('username', '');
        $password    = (string) $this->getSharedConfig('password', '');
        $messageType = $this->resolveMessageType();
        $from        = (string) $this->getChannelConfig('sms', 'from', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('Comilio credentials not configured', 'wp-sms'));
        }

        $payload = [
            'message_type'  => $messageType,
            'phone_numbers' => [$message->getRecipient()],
            'text'          => $message->getBody(),
        ];

        if ($from !== '' && $messageType !== 'Classic') {
            $payload['sender_string'] = $from;
        }

        $result = $this->httpPost(self::API_BASE . '/message', [
            'headers' => $this->authHeaders($username, $password) + [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 201 && is_array($data) && !empty($data['message_id'])) {
            return DeliveryResult::sent((string) $data['message_id']);
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Comilio credentials', 'wp-sms'));
        }

        $error = is_array($data) ? ($data['error'] ?? null) : null;

        return DeliveryResult::failed(
            $error ?: sprintf(__('Comilio send failed (HTTP %d)', 'wp-sms'), $result['code']),
        );
    }

    public function getCredit(): ?string
    {
        $username    = (string) $this->getSharedConfig('username', '');
        $password    = (string) $this->getSharedConfig('password', '');
        $messageType = $this->resolveMessageType();

        if ($username === '' || $password === '') {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/credits', [
            'headers' => $this->authHeaders($username, $password),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }
        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return null;
        }

        foreach ($data as $entry) {
            if (is_array($entry) && ($entry['message_type'] ?? null) === $messageType && isset($entry['quantity'])) {
                return (string) $entry['quantity'];
            }
        }

        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        $username    = (string) $this->getSharedConfig('username', '');
        $password    = (string) $this->getSharedConfig('password', '');
        $messageType = $this->resolveMessageType();

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('Username and Password are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/credits', [
            'headers' => $this->authHeaders($username, $password),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid username or password', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Comilio');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = null;
        foreach ($data as $entry) {
            if (is_array($entry) && ($entry['message_type'] ?? null) === $messageType && isset($entry['quantity'])) {
                $balance = (string) $entry['quantity'];
                break;
            }
        }

        if ($balance === null) {
            return TestConnectionResult::ok(
                sprintf(__('Connected — no %s credits found', 'wp-sms'), $messageType),
            );
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — %s balance: %s', 'wp-sms'), $messageType, $balance),
            ['balance' => $balance],
        );
    }

    private function resolveMessageType(): string
    {
        $configured = (string) $this->getSharedConfig('message_type', self::DEFAULT_MESSAGE_TYPE);
        return in_array($configured, self::MESSAGE_TYPES, true) ? $configured : self::DEFAULT_MESSAGE_TYPE;
    }

    /** @return array<string, string> */
    private function authHeaders(string $username, string $password): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
        ];
    }
}

<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Eskiz — Uzbekistan SMS provider. Customer dashboard at my.eskiz.uz, REST
 * API on notify.eskiz.uz/api.
 *
 * Auth: dashboard email + password are exchanged for a short-lived JWT via
 * POST /auth/login (response.data.token); the token is then sent as
 * Authorization: Bearer {token} on every subsequent request.
 *
 * Send: POST /message/sms/send body {mobile_phone, message, from}; the
 * server enforces both a pre-approved Sender ID and an exact-text-match
 * template policy — bodies that don't match an approved template are
 * rejected at send time. That template gating is a content-policy
 * constraint, not a SupportsTemplates (catalog) integration.
 *
 * Balance: GET /auth/user → response.data.balance (numeric).
 *
 * Deferred capabilities:
 *
 * @todo dlr — provider supports per-message callback_url, but webhook
 *   signature/shape is not documented in the public Postman collection;
 *   defer until verified against a live account.
 * @todo inbound — no documented MO webhook.
 * @todo verify — Eskiz exposes no Verify-as-a-Service endpoint; SupportsVerify N/A.
 * @todo token-cache — minting per call works but is wasteful; cache via
 *   transient once first end-to-end run confirms the JWT TTL.
 */
final class EskizProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://notify.eskiz.uz/api';

    public function getId(): string
    {
        return 'eskiz';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'email' => [
                    'type'        => 'string',
                    'label'       => __('Email', 'wp-sms'),
                    'required'    => true,
                    'placeholder' => 'you@example.com',
                    'description' => __('Eskiz dashboard email.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Eskiz dashboard password (used to mint a short-lived API token).', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => '4546',
                        'description' => __('Pre-approved Sender ID. Register and activate it with Eskiz support before sending.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $email    = (string) $this->getSharedConfig('email', '');
        $password = (string) $this->getSharedConfig('password', '');
        $from     = (string) $this->getChannelConfig('sms', 'from', '');

        if ($email === '' || $password === '') {
            return DeliveryResult::failed(__('Eskiz email and password are required', 'wp-sms'));
        }
        if ($from === '') {
            return DeliveryResult::failed(__('Eskiz Sender ID not configured', 'wp-sms'));
        }

        $token = $this->fetchToken();
        if ($token instanceof DeliveryResult) {
            return $token;
        }

        $result = $this->httpPost(self::API_BASE . '/message/sms/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'body' => [
                'mobile_phone' => $message->getRecipient(),
                'message'      => $message->getBody(),
                'from'         => $from,
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            $body = trim((string) $result['body']);
            $excerpt = $body !== '' ? mb_substr($body, 0, 200) : sprintf(__('HTTP %d', 'wp-sms'), $result['code']);
            return DeliveryResult::failed(sprintf(__('Eskiz send failed: %s', 'wp-sms'), $excerpt));
        }

        $json = json_decode((string) $result['body'], true);
        $id = null;
        if (is_array($json)) {
            $id = $json['data']['id'] ?? $json['id'] ?? null;
        }

        return DeliveryResult::sent($id !== null ? (string) $id : null);
    }

    public function getCredit(): ?string
    {
        $token = $this->fetchToken();
        if ($token instanceof DeliveryResult) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/auth/user', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        if ($result instanceof DeliveryResult || $result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $json = json_decode((string) $result['body'], true);
        if (!is_array($json)) {
            return null;
        }

        return (string) ($json['data']['balance'] ?? '0');
    }

    public function testConnection(): TestConnectionResult
    {
        $email    = (string) $this->getSharedConfig('email', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($email === '' || $password === '') {
            return TestConnectionResult::error(__('Email and password are required', 'wp-sms'));
        }

        $token = $this->fetchToken();
        if ($token instanceof DeliveryResult) {
            return TestConnectionResult::error($token->error ?: __('Eskiz authentication failed', 'wp-sms'));
        }

        return TestConnectionResult::ok(__('Connected to Eskiz', 'wp-sms'));
    }

    /**
     * @return string|DeliveryResult JWT on success, DeliveryResult::failed on auth failure.
     */
    private function fetchToken(): string|DeliveryResult
    {
        $email    = (string) $this->getSharedConfig('email', '');
        $password = (string) $this->getSharedConfig('password', '');

        $result = $this->httpPost(self::API_BASE . '/auth/login', [
            'body' => [
                'email'    => $email,
                'password' => $password,
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $json = json_decode((string) $result['body'], true);

        if ($result['code'] < 200 || $result['code'] >= 300) {
            $apiMessage = is_array($json) ? ($json['message'] ?? $json['error'] ?? null) : null;
            return DeliveryResult::failed(
                $apiMessage !== null
                    ? sprintf(__('Eskiz authentication failed: %s', 'wp-sms'), (string) $apiMessage)
                    : __('Eskiz authentication failed', 'wp-sms'),
            );
        }

        $token = is_array($json) ? ($json['data']['token'] ?? null) : null;
        if (!is_string($token) || $token === '') {
            return DeliveryResult::failed(__('Eskiz authentication failed: missing token in response', 'wp-sms'));
        }

        return $token;
    }
}

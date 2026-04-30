<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsInboundMessage;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

/**
 * TextAnywhere (textanywhere.com) — UK-headquartered SMS aggregator in the
 * Commify / Esendex group with global routing.
 *
 * Auth is two-step: GET /token with HTTP Basic (username:api_password) returns
 * the literal string "<user_key>;<Access_token>" which is then sent as the two
 * headers user_key / Access_token on the actual request.
 *
 * Webhooks (delivery receipts + inbound MO) are delivered as HTTP GET with
 * params in the query string and are NOT signed by the provider — verification
 * uses a configurable shared-secret token appended as ?token=... to the URL.
 * The DLR URL is set per-send via statusnotificationURL; the inbound URL is
 * configured manually in the TextAnywhere dashboard.
 */
class TextAnywhereProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage
{
    public const TESTED = false;

    private const BASE_URL = 'https://api.textanywhere.com/API/v1.0/REST';

    public function getId(): string
    {
        return 'textanywhere';
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
                    'description' => __('Your TextAnywhere account username (the email or login you sign in with).', 'wp-sms'),
                ],
                'api_password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('API Password from Account → API & IPs in the TextAnywhere dashboard. This is separate from your account login password.', 'wp-sms'),
                ],
                'message_type' => [
                    'type'     => 'select',
                    'label'    => __('Message Type', 'wp-sms'),
                    'required' => true,
                    'default'  => 'GP',
                    'options'  => [
                        ['value' => 'GP', 'label' => __('GP — Premium (guaranteed delivery)', 'wp-sms')],
                        ['value' => 'GS', 'label' => __('GS — Standard', 'wp-sms')],
                    ],
                    'description' => __('GP routes via premium (guaranteed) carriers; GS uses the standard tier.', 'wp-sms'),
                ],
                'callback_token' => [
                    'type'        => 'secret',
                    'label'       => __('Callback Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Shared secret appended to the DLR/inbound callback URLs as ?token=… so the receiver can authenticate the webhook. TextAnywhere does not sign webhooks — leave blank to disable callback handling entirely.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'MyBrand',
                        'description' => __('Approved alphanumeric Sender ID (≤11 chars, no spaces) or an E.164 number (e.g. +447700900123). Alphanumeric IDs may need pre-approval — contact TextAnywhere support.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username    = (string) $this->getSharedConfig('username');
        $apiPassword = (string) $this->getSharedConfig('api_password');
        $from        = (string) $this->getChannelConfig('sms', 'from');

        if ($username === '' || $apiPassword === '') {
            return DeliveryResult::failed(__('TextAnywhere username and API password are required', 'wp-sms'));
        }
        if ($from === '') {
            return DeliveryResult::failed(__('TextAnywhere Sender ID is required', 'wp-sms'));
        }

        $tokenOrError = $this->fetchToken($username, $apiPassword);
        if ($tokenOrError instanceof DeliveryResult) {
            return $tokenOrError;
        }
        [$userKey, $accessToken] = $tokenOrError;

        $messageType = (string) ($this->getSharedConfig('message_type') ?: 'GP');
        $callbackUrl = $this->getStatusCallbackUrlWithToken();

        $body = [
            'message_type' => $messageType,
            'message'      => $message->getBody(),
            'recipient'    => $message->getRecipient(),
            'sender'       => $from,
        ];
        if ($callbackUrl !== null) {
            $body['statusnotificationURL'] = $callbackUrl;
        }

        $result = $this->httpPost(self::BASE_URL . '/sms', [
            'headers' => $this->authedHeaders($userKey, $accessToken),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid TextAnywhere credentials', 'wp-sms'));
        }

        if (is_array($data) && isset($data['error_message'])) {
            return DeliveryResult::failed((string) $data['error_message']);
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(sprintf('HTTP %d', $result['code']));
        }

        $orderId = isset($data['order_id']) ? (string) $data['order_id'] : null;

        // Final state arrives via the DLR webhook (600-range = intermediate,
        // 400/500-range = terminal); until then mark as queued.
        return DeliveryResult::queued($orderId);
    }

    public function getCredit(): ?string
    {
        $username    = (string) $this->getSharedConfig('username');
        $apiPassword = (string) $this->getSharedConfig('api_password');
        if ($username === '' || $apiPassword === '') {
            return null;
        }

        $tokenOrError = $this->fetchToken($username, $apiPassword);
        if ($tokenOrError instanceof DeliveryResult) {
            return null;
        }
        [$userKey, $accessToken] = $tokenOrError;

        $result = $this->httpGet(self::BASE_URL . '/status', [
            'headers' => $this->authedHeaders($userKey, $accessToken),
        ]);

        if ($result instanceof DeliveryResult || $result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || empty($data['sms']) || !is_array($data['sms'])) {
            return null;
        }

        $parts = [];
        foreach ($data['sms'] as $entry) {
            if (!is_array($entry) || !isset($entry['type'], $entry['quantity'])) {
                continue;
            }
            $parts[] = sprintf('%s: %s', (string) $entry['type'], (string) $entry['quantity']);
        }

        return $parts ? implode(', ', $parts) : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $username    = (string) $this->getSharedConfig('username');
        $apiPassword = (string) $this->getSharedConfig('api_password');
        if ($username === '' || $apiPassword === '') {
            return TestConnectionResult::error(__('Username and API Password are required', 'wp-sms'));
        }

        $tokenOrError = $this->fetchToken($username, $apiPassword);
        if ($tokenOrError instanceof DeliveryResult) {
            return TestConnectionResult::error($tokenOrError->error ?? __('Invalid TextAnywhere credentials', 'wp-sms'));
        }
        [$userKey, $accessToken] = $tokenOrError;

        $result = $this->httpGet(self::BASE_URL . '/status', [
            'headers' => $this->authedHeaders($userKey, $accessToken),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid TextAnywhere credentials', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'TextAnywhere');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = $this->formatCreditSummary($data);
        $message = $balance !== null
            ? sprintf(__('Connected — Credit: %s', 'wp-sms'), $balance)
            : __('Connected', 'wp-sms');

        return TestConnectionResult::ok($message, ['balance' => $balance]);
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->validateCallbackToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $providerId = (string) ($request->get_param('messagereference') ?? '');
        $codeParam  = $request->get_param('messagestatuscode');

        if ($providerId === '' || $codeParam === null || $codeParam === '') {
            return [];
        }

        $code = (int) $codeParam;
        [$status, $permanent] = match (true) {
            $code >= 400 && $code < 500 => ['delivered', true],
            $code >= 500 && $code < 600 => ['failed',    true],
            $code >= 600 && $code < 700 => ['sent',      false],
            default                     => ['failed',    false],
        };

        // Code 515 = "Destination opted-out" — flip the contact via OptOutManager
        // / StatusPropagator by setting unsubscribe=true on the StatusUpdate.
        $unsubscribe = $code === 515;

        return [new StatusUpdate(
            providerId:   $providerId,
            status:       $status,
            errorCode:    (string) $code,
            errorMessage: $status === 'failed' ? sprintf('TextAnywhere status code %d', $code) : null,
            permanent:    $permanent,
            unsubscribe:  $unsubscribe,
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->validateCallbackToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $from = (string) ($request->get_param('Originator') ?? '');
        if ($from === '') {
            return [];
        }

        $rbid = $request->get_param('RBID');

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($request->get_param('Destination') ?? ''),
            body:       (string) ($request->get_param('Body') ?? ''),
            providerId: $rbid !== null && $rbid !== '' ? (string) $rbid : null,
            meta:       array_filter([
                'date' => $request->get_param('Date'),
                'time' => $request->get_param('Time'),
            ], static fn($v) => $v !== null && $v !== ''),
        )];
    }

    // --- Internal ---

    /**
     * GET /token returns the literal body "<user_key>;<Access_token>".
     *
     * @return array{0: string, 1: string}|DeliveryResult
     */
    private function fetchToken(string $username, string $apiPassword): array|DeliveryResult
    {
        $result = $this->httpGet(self::BASE_URL . '/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $apiPassword),
                'Content-Type'  => 'application/json',
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid TextAnywhere credentials', 'wp-sms'));
        }

        $body = trim((string) $result['body']);
        if ($body === '' || !str_contains($body, ';')) {
            return DeliveryResult::failed(__('Unexpected token response from TextAnywhere', 'wp-sms'));
        }

        [$userKey, $accessToken] = array_pad(explode(';', $body, 2), 2, '');
        if ($userKey === '' || $accessToken === '') {
            return DeliveryResult::failed(__('Unexpected token response from TextAnywhere', 'wp-sms'));
        }

        return [$userKey, $accessToken];
    }

    /** @return array<string, string> */
    private function authedHeaders(string $userKey, string $accessToken): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'user_key'     => $userKey,
            'Access_token' => $accessToken,
        ];
    }

    private function getStatusCallbackUrlWithToken(): ?string
    {
        $token = $this->getSharedConfig('callback_token');
        if (!is_string($token) || $token === '') {
            return null;
        }
        return RestRoute::url(
            'callbacks/' . $this->getId() . '/status',
            ['token' => $token],
        );
    }

    private function validateCallbackToken(\WP_REST_Request $request): bool
    {
        $expected = $this->getSharedConfig('callback_token');
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        $supplied = (string) ($request->get_param('token') ?? '');
        if ($supplied === '') {
            return false;
        }
        return hash_equals($expected, $supplied);
    }

    private function formatCreditSummary(array $data): ?string
    {
        if (empty($data['sms']) || !is_array($data['sms'])) {
            return null;
        }
        $parts = [];
        foreach ($data['sms'] as $entry) {
            if (!is_array($entry) || !isset($entry['type'], $entry['quantity'])) {
                continue;
            }
            $parts[] = sprintf('%s: %s', (string) $entry['type'], (string) $entry['quantity']);
        }
        return $parts ? implode(', ', $parts) : null;
    }
}

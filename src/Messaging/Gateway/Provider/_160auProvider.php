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
 * 160AU (160.com.au) — Australian SMS aggregator with global delivery.
 *
 * Auth: HTTP Basic with the account username + the dedicated "SMS Secret"
 * generated under Profile on www.160.com.au (separate from the login password).
 * Send: POST /v1/messages with a JSON body of `{messages:[{recipient,message,…}]}`.
 *       Per-message statusCode of -1/0/1/2 indicates queued vs error categories.
 * Balance: GET /v1/info — returns balance, sender IDs, virtual numbers.
 * DLR: per-message `callbackURL` field; provider POSTs back. Unsigned, so the
 *      endpoint is authenticated by a shared `?token=…` we generate.
 * Inbound MO: per-virtual-number `callbackURL` registered in the dashboard.
 *      Unsigned — same shared-token pattern as the DLR, with a separate token
 *      so the two channels can be rotated independently.
 *
 * v7 → v8 reconciliation: v7 hit the legacy SOAP-style endpoint at
 * www.160.com.au/api/sms.asmx/ with form-encoded `username`/`password` fields
 * and parsed XML responses prefixed with "ERR:". The provider now publishes a
 * modern REST API (api.160.com.au/v1) which is canonical per its OpenAPI spec
 * — v8 ships against that. Field renames: mobileNumber→recipient,
 * senderName→senderId, messageText→message; balance moved from a
 * POST GetCreditBalance form call to GET /v1/info → `balance`.
 */
// TODO(verify): provider has /v1/otp/{otpId}/send + /v1/otp/{otpId}/verify;
// defer until SupportsVerify lands.
class _160auProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.160.com.au/v1';

    public function getId(): string
    {
        return '160au';
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
                    'description' => __('Your 160.com.au account username.', 'wp-sms'),
                ],
                'sms_secret' => [
                    'type'        => 'secret',
                    'label'       => __('SMS Secret', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generated under Profile → SMS Secret on your 160.com.au account. This is separate from your login password.', 'wp-sms'),
                ],
                'callback_token_dlr' => [
                    'type'        => 'secret',
                    'label'       => __('Status Callback Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Random shared secret appended as ?token=<value> to the delivery-report URL. 160AU does not sign webhooks, so the token is what authenticates them.', 'wp-sms'),
                ],
                'callback_token_mo' => [
                    'type'        => 'secret',
                    'label'       => __('Inbound Callback Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Random shared secret appended as ?token=<value> to the inbound URL set against your virtual number. Use a different value than the Status Callback Token so the two channels can be rotated independently.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => false,
                        'placeholder' => 'MyBrand',
                        'description' => __('Approved alphanumeric Sender ID (max 11 chars). Leave blank to use the account default.', 'wp-sms'),
                    ],
                    'virtual_number' => [
                        'type'        => 'string',
                        'label'       => __('Virtual Number', 'wp-sms'),
                        'required'    => false,
                        'placeholder' => '+61400000000',
                        'description' => __('Your 160AU virtual number (E.164). Required if you want recipients to be able to reply.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'delivery_receipt' => true,
            'incoming'         => true,
            'unicode'          => true,
            'test_connection'  => true,
        ]);
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = $this->getSharedConfig('username');
        $secret   = $this->getSharedConfig('sms_secret');

        if (!$username || !$secret) {
            return DeliveryResult::failed(__('160AU credentials not configured', 'wp-sms'));
        }

        $entry = [
            'recipient' => $message->getRecipient(),
            'message'   => $message->getBody(),
        ];

        $senderId = $this->getChannelConfig('sms', 'sender_id');
        if ($senderId) {
            $entry['senderId'] = (string) $senderId;
        }

        $virtualNumber = $this->getChannelConfig('sms', 'virtual_number');
        if ($virtualNumber) {
            $entry['phone'] = (string) $virtualNumber;
        }

        $dlrToken = $this->getSharedConfig('callback_token_dlr');
        if ($dlrToken) {
            $entry['callbackURL'] = $this->getStatusCallbackUrl();
        }

        $result = $this->httpPost(self::API_BASE . '/messages', [
            'headers' => $this->authHeaders((string) $username, (string) $secret) + [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode(['messages' => [$entry]]),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid 160AU username or SMS Secret', 'wp-sms'));
        }

        if ($result['code'] === 429) {
            return DeliveryResult::failed(
                __('160AU rate limit hit (max 2 requests/second)', 'wp-sms'),
                retryable: true,
            );
        }

        $data = json_decode($result['body'], true);
        $first = is_array($data) ? ($data['messages'][0] ?? null) : null;

        if ($result['code'] >= 200 && $result['code'] < 300 && is_array($first)) {
            $statusCode = (int) ($first['statusCode'] ?? -1);
            $providerId = isset($first['messageId']) ? (string) $first['messageId'] : null;

            // Per 160AU: 0 = success/queued, 1 = warning (queued with caveats),
            // 2 = invalid recipient, -1 = generic error.
            if ($statusCode === 0 || $statusCode === 1) {
                return DeliveryResult::queued($providerId);
            }

            $statusText = (string) ($first['status'] ?? sprintf('statusCode %d', $statusCode));
            return DeliveryResult::failed(
                sprintf(__('160AU rejected the message: %s', 'wp-sms'), $statusText),
                meta: array_filter([
                    '160au_status_code' => (string) $statusCode,
                    '160au_status'      => $first['status'] ?? null,
                ]),
                retryable: $statusCode === -1,
            );
        }

        $errorText = is_array($data) ? ($data['message'] ?? $data['error'] ?? null) : null;
        return DeliveryResult::failed(
            $errorText ?: sprintf('HTTP %d', $result['code']),
        );
    }

    public function getCredit(): ?string
    {
        $username = $this->getSharedConfig('username');
        $secret   = $this->getSharedConfig('sms_secret');

        if (!$username || !$secret) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/info', [
            'headers' => $this->authHeaders((string) $username, (string) $secret),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['balance'])) {
            return null;
        }

        return number_format((float) $data['balance'], 2) . ' credits';
    }

    public function testConnection(): TestConnectionResult
    {
        $username = $this->getSharedConfig('username');
        $secret   = $this->getSharedConfig('sms_secret');

        if (!$username || !$secret) {
            return TestConnectionResult::error(__('Username and SMS Secret are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/info', [
            'headers' => $this->authHeaders((string) $username, (string) $secret),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid 160AU username or SMS Secret', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, '160AU');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (isset($data['balance'])) {
            return TestConnectionResult::ok(
                sprintf(__('Connected — Balance: %s', 'wp-sms'), number_format((float) $data['balance'], 2)),
                ['balance' => $data['balance']],
            );
        }

        return TestConnectionResult::ok(__('Connected to 160AU', 'wp-sms'));
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        $token = $this->getSharedConfig('callback_token_dlr');
        $args = $token ? ['token' => $token] : [];
        return RestRoute::url('callbacks/' . $this->getId() . '/status', $args);
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyToken($request, 'callback_token_dlr');
    }

    /**
     * Parse a 160AU DLR. The OpenAPI spec describes the per-message callbackURL
     * but doesn't pin the exact webhook payload field names; treat documented
     * candidates (messageId + status) and degrade unknown statuses to "sent"
     * rather than dropping the update.
     *
     * TODO(verify): confirm messageId/status field names against a live DLR
     * payload during manual testing and tighten if the live shape differs.
     *
     * @return StatusUpdate[]
     */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $providerId = (string) ($request->get_param('messageId') ?? $request->get_param('id') ?? '');
        $rawStatus  = strtolower((string) ($request->get_param('status') ?? ''));

        if ($providerId === '' || $rawStatus === '') {
            return [];
        }

        $normalized = match (true) {
            in_array($rawStatus, ['delivered', 'success', 'ok'], true)        => 'delivered',
            in_array($rawStatus, ['failed', 'undelivered', 'expired'], true)  => 'failed',
            default                                                            => 'sent',
        };

        return [new StatusUpdate(
            providerId:   $providerId,
            status:       $normalized,
            errorCode:    $normalized === 'failed' ? $rawStatus : null,
            errorMessage: $normalized === 'failed' ? sprintf(__('160AU: %s', 'wp-sms'), $rawStatus) : null,
            permanent:    $normalized === 'failed',
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        $token = $this->getSharedConfig('callback_token_mo');
        $args = $token ? ['token' => $token] : [];
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound', $args);
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyToken($request, 'callback_token_mo');
    }

    /**
     * Parse a 160AU inbound MO. Field names aren't pinned in the spec; try the
     * canonical REST names (sender/recipient/message) and fall back to common
     * aliases.
     *
     * TODO(verify): confirm field names against a live MO payload during manual
     * testing.
     *
     * @return InboundMessage[]
     */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $from = (string) ($request->get_param('sender') ?? $request->get_param('from') ?? '');
        if ($from === '') {
            return [];
        }

        $to   = (string) ($request->get_param('recipient') ?? $request->get_param('to') ?? '');
        $body = (string) ($request->get_param('message') ?? $request->get_param('text') ?? '');
        $messageId = $request->get_param('messageId') ?? $request->get_param('id');

        return [new InboundMessage(
            from:       $from,
            to:         $to,
            body:       $body,
            providerId: $messageId !== null ? (string) $messageId : null,
        )];
    }

    // --- Internal ---

    private function authHeaders(string $username, string $password): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode("{$username}:{$password}"),
        ];
    }

    private function verifyToken(\WP_REST_Request $request, string $configKey): bool
    {
        $expected = (string) $this->getSharedConfig($configKey, '');
        if ($expected === '') {
            return false;
        }
        $given = (string) ($request->get_param('token') ?? '');
        if ($given === '') {
            return false;
        }
        return hash_equals($expected, $given);
    }
}

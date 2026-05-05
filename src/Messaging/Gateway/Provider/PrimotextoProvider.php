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
 * Primotexto — French SMS gateway (Orange Business). Free trial: 50 SMS, no card.
 *
 * Auth: header `X-Primotexto-ApiKey: {key}` (per the Symfony Notifier
 * `PrimotextoTransport` and the Primotexto authentication doc).
 *
 * Send (transactional): POST https://api.primotexto.com/v2/notification/messages/send
 * Send (marketing):     POST https://api.primotexto.com/v2/marketing/messages/send
 *   body { number, message, sender?, category? } → 200 { creditsUsed, snapshotId }
 * Balance:              GET  https://api.primotexto.com/v2/account/stats
 *
 * Webhooks: Primotexto allows exactly one webhook URL per account, configured
 * in the dashboard UI (no register-webhook API). All event types fire there.
 * The user picks WSMS's status callback URL (DLR + opt-out) or inbound URL
 * (replies). Both interfaces demux by the `event` field so the wrong one
 * silently no-ops instead of misclassifying.
 *
 * Webhook signing is not documented; we mitigate by embedding an HMAC token
 * tied to the API key as a `?token=…` query arg, identical to the proven
 * SpotHit / EasySendSMS pattern.
 */
class PrimotextoProvider extends AbstractProvider implements SupportsStatusCallback, SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    // TODO(opt-out): Primotexto's PrimotextoErrorCode enum (Symfony bridge)
    // includes a "send to blacklisted number" code, but the exact integer is
    // not pinned in public docs. Implement SupportsOptOutDetection once the
    // synchronous error code is observed end-to-end.

    private const API_BASE = 'https://api.primotexto.com/v2';

    public function getId(): string
    {
        return 'primotexto';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generate under My Account → Authentication in your Primotexto dashboard.', 'wp-sms'),
                ],
                'mode' => [
                    'type'        => 'select',
                    'label'       => __('Mode', 'wp-sms'),
                    'required'    => false,
                    'default'     => 'notification',
                    'description' => __('Notification routes through the transactional pool. Marketing uses the marketing pool — French regulation requires marketing SMS to include a "STOP au XXXXX" clause.', 'wp-sms'),
                    'options'     => [
                        ['value' => 'notification', 'label' => __('Notification (transactional)', 'wp-sms')],
                        ['value' => 'marketing',    'label' => __('Marketing', 'wp-sms')],
                    ],
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'WSMS',
                        'description' => __('3–11 alphanumeric characters. Must contain at least one letter — fully numeric senders are rejected by Primotexto with error 23.', 'wp-sms'),
                    ],
                    'category' => [
                        'type'        => 'string',
                        'label'       => __('Category', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Optional dashboard-only label for grouping sends. Not visible to recipients.', 'wp-sms'),
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
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return DeliveryResult::failed(__('Primotexto credentials not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'from', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('Primotexto Sender ID not configured', 'wp-sms'));
        }

        $mode = $this->getSharedConfig('mode', 'notification') === 'marketing' ? 'marketing' : 'notification';

        $body = [
            'number'  => $message->getRecipient(),
            'message' => $message->getBody(),
            'sender'  => $sender,
        ];

        $category = $this->getChannelConfig('sms', 'category');
        if (!empty($category)) {
            $body['category'] = $category;
        }

        $result = $this->httpPost(self::API_BASE . '/' . $mode . '/messages/send', [
            'headers' => $this->authHeaders($apiKey),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Primotexto API Key', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] >= 200 && $result['code'] < 300 && is_array($data) && !empty($data['snapshotId'])) {
            return DeliveryResult::sent(
                providerId: (string) $data['snapshotId'],
                cost: isset($data['creditsUsed']) ? (float) $data['creditsUsed'] : null,
            );
        }

        $errorCode = is_array($data) ? ($data['code'] ?? null) : null;
        $errorMsg  = is_array($data) ? ($data['message'] ?? null) : null;

        // Error code buckets per Symfony PrimotextoErrorCode enum:
        // 10–19 phone, 20–23 sender, 30–32 message, 40–48 campaign,
        // 70–76 auth/quota, 90–95 country.
        return DeliveryResult::failed(
            $errorMsg ?: sprintf(__('Primotexto rejected the request (HTTP %d)', 'wp-sms'), $result['code']),
            meta: array_filter([
                'primotexto_error_code' => $errorCode,
                'http_code'             => $result['code'],
            ], fn($v) => $v !== null),
        );
    }

    public function getCredit(): ?string
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/account/stats', [
            'headers' => $this->authHeaders($apiKey),
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

        $credits = $data['credits'] ?? $data['balance'] ?? null;
        return $credits === null ? null : (string) $credits;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/account/stats', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Primotexto API Key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Primotexto');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = $data['credits'] ?? $data['balance'] ?? 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s credits', 'wp-sms'), (string) $balance),
            ['balance' => (string) $balance],
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url(
            'callbacks/' . $this->getId() . '/status',
            ['token' => $this->callbackToken()],
        );
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->validateToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $payload = $request->get_json_params();
        if (empty($payload['snapshotId']) || empty($payload['event'])) {
            return [];
        }

        $event = (string) $payload['event'];

        // `reply` is delivered to the inbound parser; `opened` doesn't map onto
        // an outbound delivery state. Skip both here.
        if ($event === 'reply' || $event === 'opened') {
            return [];
        }

        $status = match ($event) {
            'submitted'    => 'queued',
            'sent'         => 'sent',
            'delivered'    => 'delivered',
            'bounced',
            'unsubscribed',
            'error'        => 'failed',
            default        => $event,
        };

        $permanent = in_array($event, ['bounced', 'unsubscribed'], true);
        $unsubscribe = $event === 'unsubscribed';

        return [new StatusUpdate(
            providerId:   (string) $payload['snapshotId'],
            status:       $status,
            errorCode:    $status === 'failed' ? $event : null,
            errorMessage: $status === 'failed'
                ? sprintf(__('Primotexto event=%s', 'wp-sms'), $event)
                : null,
            permanent:    $permanent,
            unsubscribe:  $unsubscribe,
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url(
            'callbacks/' . $this->getId() . '/inbound',
            ['token' => $this->callbackToken()],
        );
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->validateToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $payload = $request->get_json_params();
        if (($payload['event'] ?? null) !== 'reply') {
            return [];
        }

        $contact = $payload['contact'] ?? [];
        $from = is_array($contact) ? (string) ($contact['identifier'] ?? '') : '';
        $body = (string) ($payload['replyMessage'] ?? '');

        if ($from === '' || $body === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) $this->getChannelConfig('sms', 'from', ''),
            body:       $body,
            providerId: !empty($payload['snapshotId']) ? (string) $payload['snapshotId'] : null,
        )];
    }

    // --- Internal ---

    /** @return array<string, string> */
    private function authHeaders(string $apiKey): array
    {
        return [
            'X-Primotexto-ApiKey' => $apiKey,
            'Content-Type'        => 'application/json',
            'Accept'              => 'application/json',
        ];
    }

    private function callbackToken(): string
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return '';
        }
        return hash_hmac('sha256', 'primotexto-callback', $apiKey);
    }

    private function validateToken(\WP_REST_Request $request): bool
    {
        $expected = $this->callbackToken();
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

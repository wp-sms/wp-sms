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
 * Fortytwo Telecom — Advanced Messaging Platform (SMS + Viber).
 *
 * One endpoint, two channels: POST https://rest.fortytwo.com/1/im accepts
 * either `sms_content` or `im_content` (channel=VIBER) on the same auth
 * token. Verified against the official PHP SDK at github.com/42Telecom
 * (php-sdk-advanced-messaging-platform + php-sdk-core).
 *
 * Webhook signing: FortyTwo does not sign callbacks. We follow the WSMS
 * fail-closed URL-token pattern — admins paste a Webhook Token here, it
 * gets appended as ?token=<...> to the callback/reply URLs sent in the
 * request body, and incoming callbacks without a matching token are
 * rejected with 403.
 *
 * TODO(verify): provider has /1/2fa (Verify-as-a-Service: requestCode +
 * validateCode) — defer until WSMS adds SupportsVerify. Cannot be wired
 * through SupportsTemplates because the /1/im send endpoint doesn't
 * accept template_id+vars; OTPs go through a separate verify lifecycle.
 */
class FortyTwoProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://rest.fortytwo.com/1';

    public function getId(): string
    {
        return 'fortytwo';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'viber'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_token' => [
                    'type'        => 'secret',
                    'label'       => __('API Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generate at controlpanel.fortytwo.com under IM > Tokens.', 'wp-sms'),
                ],
                'webhook_token' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Shared secret appended to callback URLs as ?token=. FortyTwo does not sign webhooks; this token is required to accept delivery and inbound callbacks. Leave blank to disable callbacks.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Default Sender ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Alphanumeric (1-11 chars) or numeric (1-20 digits). Leave blank to use the account default.', 'wp-sms'),
                        'placeholder' => 'MyCompany',
                    ],
                ],
                'viber' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Viber Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Approved Viber Business sender registered with FortyTwo. Required — Viber rejects messages without a registered sender.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $token = $this->getSharedConfig('api_token');
        if (!$token) {
            return DeliveryResult::failed(__('FortyTwo API Token not configured', 'wp-sms'));
        }

        $channel = $message->getChannel();
        return match ($channel) {
            'sms'   => $this->sendSms($message, $token),
            'viber' => $this->sendViber($message, $token),
            default => DeliveryResult::failed(sprintf(__('FortyTwo does not support channel %s', 'wp-sms'), $channel)),
        };
    }

    private function sendSms(MessageInterface $message, string $token): DeliveryResult
    {
        $body = [
            'destinations' => [['number' => $this->normaliseNumber($message->getRecipient())]],
            'sms_content'  => array_filter([
                'message'   => $message->getBody(),
                'sender_id' => $this->getChannelConfig('sms', 'sender_id'),
            ], fn($v) => $v !== null && $v !== ''),
        ];

        $this->attachWebhookUrls($body);

        return $this->postSend($body, $token, $message->getRecipient());
    }

    private function sendViber(MessageInterface $message, string $token): DeliveryResult
    {
        $sender = $this->getChannelConfig('viber', 'sender_id');
        if (!$sender) {
            return DeliveryResult::failed(__('FortyTwo Viber Sender ID is required', 'wp-sms'));
        }

        $meta = $message->getMeta();
        $imContent = [
            'channel'   => 'VIBER',
            'sender_id' => $sender,
            'content'   => $message->getBody(),
        ];

        // Optional Viber media via meta — matches IMImageEntity in the SDK.
        $imageUrl = $meta['media_urls'][0] ?? ($meta['viber_image_url'] ?? null);
        if ($imageUrl) {
            $imContent['images'] = [['url' => (string) $imageUrl]];
        }

        // Optional Viber CTA button via meta — matches IMActionEntity.
        if (!empty($meta['viber_action_url']) && !empty($meta['viber_action_title'])) {
            $imContent['actions'] = [[
                'title'      => (string) $meta['viber_action_title'],
                'target_url' => (string) $meta['viber_action_url'],
            ]];
        }

        $body = [
            'destinations' => [['number' => $this->normaliseNumber($message->getRecipient())]],
            'im_content'   => [$imContent],
        ];

        $this->attachWebhookUrls($body);

        return $this->postSend($body, $token, $message->getRecipient());
    }

    private function postSend(array $body, string $token, string $recipient): DeliveryResult
    {
        $result = $this->httpPost(self::API_BASE . '/im', [
            'headers' => $this->authHeaders($token, 'application/json; charset=utf-8'),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true) ?: [];

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid FortyTwo API Token', 'wp-sms'));
        }

        // FortyTwo's documented success is HTTP 201 (per SDK's mock fixture);
        // accept any 2xx defensively.
        if ($result['code'] >= 200 && $result['code'] < 300) {
            $providerId = $this->extractMessageId($data, $recipient);
            return DeliveryResult::queued($providerId);
        }

        $errorMsg = $data['result_info']['description']
            ?? $data['error']
            ?? sprintf('HTTP %d', $result['code']);

        return DeliveryResult::failed(
            (string) $errorMsg,
            meta: array_filter([
                'fortytwo_status' => $result['code'] ?: null,
                'fortytwo_code'   => isset($data['result_info']['status_code'])
                    ? (string) $data['result_info']['status_code']
                    : null,
            ]),
        );
    }

    private function extractMessageId(array $data, string $recipient): ?string
    {
        $results = $data['results'] ?? null;
        if (!is_array($results)) {
            return null;
        }

        // results is keyed by destination number — try the recipient as sent
        // and a digits-only variant (FortyTwo strips '+' in keys).
        $candidates = [$recipient, ltrim($recipient, '+')];
        foreach ($candidates as $key) {
            if (isset($results[$key]['message_id'])) {
                return (string) $results[$key]['message_id'];
            }
        }

        // Fallback: take the first entry if there's only one destination.
        $first = reset($results);
        return is_array($first) && isset($first['message_id'])
            ? (string) $first['message_id']
            : null;
    }

    private function normaliseNumber(string $recipient): string
    {
        // FortyTwo expects an international-format number with no leading +.
        // SDK's NumberValue accepts 7-20 digits, first digit non-zero.
        return ltrim($recipient, '+');
    }

    private function attachWebhookUrls(array &$body): void
    {
        $statusUrl  = $this->getStatusCallbackUrl();
        $inboundUrl = $this->getInboundCallbackUrl();
        if ($statusUrl !== '') {
            $body['callback_url'] = $statusUrl;
        }
        if ($inboundUrl !== '') {
            $body['reply_url'] = $inboundUrl;
        }
    }

    public function testConnection(): TestConnectionResult
    {
        $token = $this->getSharedConfig('api_token');
        if (!$token) {
            return TestConnectionResult::error(__('API Token is required', 'wp-sms'));
        }

        // FortyTwo has no public balance/account endpoint, but GET /im/status/{id}
        // requires auth — probe with an arbitrary ID and treat 401/403 as "bad
        // token" while any other response (typically 4xx for unknown ID) means
        // the token authenticated successfully.
        $result = $this->httpGet(self::API_BASE . '/im/status/wsms-probe', [
            'headers' => $this->authHeaders($token),
        ]);

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the FortyTwo API. Check your server\'s internet connection.', 'wp-sms'),
            );
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return TestConnectionResult::error(__('Invalid FortyTwo API Token', 'wp-sms'));
        }

        return TestConnectionResult::ok(__('FortyTwo API Token is valid', 'wp-sms'));
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return $this->buildCallbackUrl('status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyWebhookToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $payload = $request->get_json_params();
        if (!is_array($payload) || empty($payload['data']) || !is_array($payload['data'])) {
            return [];
        }

        $updates = [];
        foreach ($payload['data'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $providerId = $entry['message_id'] ?? null;
            $rawStatus  = $entry['status'] ?? null;
            if (!$providerId || !$rawStatus) {
                continue;
            }

            $upper = strtoupper((string) $rawStatus);
            $normalized = match ($upper) {
                'QUEUED', 'BUFFERED', 'PENDING'                   => 'queued',
                'ACCEPTED', 'SUBMITTED', 'SENT'                   => 'sent',
                'DELIVERED', 'SEEN', 'READ'                       => 'delivered',
                'REJECTED', 'FAILED', 'EXPIRED', 'UNDELIVERABLE'  => 'failed',
                default                                           => strtolower((string) $rawStatus),
            };

            $errorCode = isset($entry['error_code']) && $entry['error_code'] !== '' && $entry['error_code'] !== 0
                ? (string) $entry['error_code']
                : null;

            $updates[] = new StatusUpdate(
                providerId:   (string) $providerId,
                status:       $normalized,
                errorCode:    $errorCode,
                errorMessage: $normalized === 'failed' ? sprintf('FortyTwo: %s', $upper) : null,
                permanent:    in_array($upper, ['REJECTED', 'FAILED', 'EXPIRED', 'UNDELIVERABLE'], true),
            );
        }

        return $updates;
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return $this->buildCallbackUrl('inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyWebhookToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            return [];
        }

        // FortyTwo's reply payload field names aren't documented in the public
        // SDK, so accept the common shapes. Manual verification (step 8 of the
        // plan) will pin the exact field names; update here if needed.
        $from = (string) ($payload['from'] ?? $payload['source'] ?? $payload['msisdn'] ?? '');
        if ($from === '') {
            return [];
        }

        $body = (string) ($payload['message'] ?? $payload['content'] ?? $payload['text'] ?? '');

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($payload['to'] ?? $payload['destination'] ?? ''),
            body:       $body,
            providerId: isset($payload['message_id']) ? (string) $payload['message_id'] : null,
            meta:       array_filter([
                'type'        => $payload['type'] ?? null,
                'received_at' => $payload['timestamp'] ?? $payload['received_at'] ?? null,
            ]),
        )];
    }

    // --- Internal ---

    private function authHeaders(string $token, ?string $contentType = null): array
    {
        $headers = [
            'Authorization' => 'Token ' . $token,
            'Accept'        => 'application/json',
        ];
        if ($contentType) {
            $headers['Content-Type'] = $contentType;
        }
        return $headers;
    }

    private function buildCallbackUrl(string $kind): string
    {
        $token = $this->getSharedConfig('webhook_token');
        $args  = $token ? ['token' => $token] : [];
        return RestRoute::url('callbacks/' . $this->getId() . '/' . $kind, $args);
    }

    private function verifyWebhookToken(\WP_REST_Request $request): bool
    {
        $configured = $this->getSharedConfig('webhook_token');
        if (!is_string($configured) || $configured === '') {
            // Fail closed — accepting unsigned callbacks would let anyone forge
            // delivery / inbound events for this account.
            return false;
        }

        $received = $request->get_param('token');
        if (!is_string($received) || $received === '') {
            return false;
        }

        return hash_equals($configured, $received);
    }
}

<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsInboundMessage;
use WSms\Messaging\Contracts\SupportsOptOutDetection;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

/**
 * Sinch — multi-channel.
 *
 * SMS uses the legacy XMS REST API (Bearer auth, host us|eu.sms.api.sinch.com).
 * WhatsApp + RCS use the Conversations API (Basic auth, host us|eu|br.conversation.api.sinch.com).
 *
 * Webhook signatures differ per API and are handled in validateStatusCallback /
 * validateInboundCallback. Sinch supports a single Conversations webhook URL for
 * both delivery + inbound events; we tell users to register two webhooks filtered
 * to MESSAGE_DELIVERY → /status and MESSAGE_INBOUND → /inbound.
 */
class SinchProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsOptOutDetection
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    public function getId(): string
    {
        return 'sinch';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp', 'rcs'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'region' => [
                    'type'    => 'select',
                    'label'   => __('Region', 'wp-sms'),
                    'default' => 'us',
                    'options' => [
                        ['value' => 'us', 'label' => 'US'],
                        ['value' => 'eu', 'label' => 'EU'],
                        ['value' => 'br', 'label' => 'BR (Conversations API only)'],
                    ],
                    'description' => __('Must match the region your Sinch app was created in.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'service_plan_id' => [
                        'type'        => 'string',
                        'label'       => __('Service Plan ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('From the Sinch dashboard under SMS > APIs.', 'wp-sms'),
                    ],
                    'api_token' => [
                        'type'        => 'secret',
                        'label'       => __('API Token', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Bearer token for the SMS API, generated under SMS > APIs.', 'wp-sms'),
                    ],
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Default Originator', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Sender ID or virtual number. Optional if a default is set on the service plan.', 'wp-sms'),
                        'placeholder' => '+15551234567',
                    ],
                    'callback_secret' => [
                        'type'        => 'secret',
                        'label'       => __('Callback HMAC Secret', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Optional. If you enable HMAC callback signing on your service plan, paste the secret here. Leave blank for unsigned callbacks.', 'wp-sms'),
                    ],
                ],
                'whatsapp' => [
                    'project_id' => [
                        'type'        => 'string',
                        'label'       => __('Project ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Conversations API Project ID from Settings > Access Keys.', 'wp-sms'),
                    ],
                    'access_key_id' => [
                        'type'        => 'string',
                        'label'       => __('Access Key ID', 'wp-sms'),
                        'required'    => true,
                    ],
                    'access_key_secret' => [
                        'type'        => 'secret',
                        'label'       => __('Access Key Secret', 'wp-sms'),
                        'required'    => true,
                    ],
                    'app_id' => [
                        'type'        => 'string',
                        'label'       => __('App ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('The Conversations app holding your WhatsApp Business sender.', 'wp-sms'),
                    ],
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('WhatsApp Number', 'wp-sms'),
                        'required'    => false,
                        'description' => __('The WhatsApp business number assigned to the app (informational; routing uses App ID).', 'wp-sms'),
                    ],
                    'webhook_secret' => [
                        'type'        => 'secret',
                        'label'       => __('Webhook Secret', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Shared secret you set when creating the webhook in the Conversations app. Used to verify HMAC-SHA256 signatures.', 'wp-sms'),
                    ],
                ],
                'rcs' => [
                    'project_id' => [
                        'type'     => 'string',
                        'label'    => __('Project ID', 'wp-sms'),
                        'required' => true,
                    ],
                    'access_key_id' => [
                        'type'     => 'string',
                        'label'    => __('Access Key ID', 'wp-sms'),
                        'required' => true,
                    ],
                    'access_key_secret' => [
                        'type'     => 'secret',
                        'label'    => __('Access Key Secret', 'wp-sms'),
                        'required' => true,
                    ],
                    'app_id' => [
                        'type'        => 'string',
                        'label'       => __('App ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('The Conversations app holding your RCS sender.', 'wp-sms'),
                    ],
                    'from' => [
                        'type'     => 'string',
                        'label'    => __('RCS Agent ID', 'wp-sms'),
                        'required' => false,
                    ],
                    'webhook_secret' => [
                        'type'        => 'secret',
                        'label'       => __('Webhook Secret', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Shared secret you set when creating the webhook. Used to verify HMAC-SHA256 signatures.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $channel = $message->getChannel();
        return match ($channel) {
            'sms'                 => $this->sendSms($message),
            'whatsapp', 'rcs'     => $this->sendConversation($message, $channel),
            default               => DeliveryResult::failed(sprintf(__('Sinch does not support channel %s', 'wp-sms'), $channel)),
        };
    }

    private function sendSms(MessageInterface $message): DeliveryResult
    {
        $servicePlanId = $this->getChannelConfig('sms', 'service_plan_id');
        $apiToken = $this->getChannelConfig('sms', 'api_token');

        if (!$servicePlanId || !$apiToken) {
            return DeliveryResult::failed(__('Sinch SMS credentials not configured', 'wp-sms'));
        }

        $body = [
            'to'           => [$message->getRecipient()],
            'body'         => $message->getBody(),
            'callback_url' => $this->getStatusCallbackUrl(),
        ];

        $from = $this->getChannelConfig('sms', 'from');
        if ($from) {
            $body['from'] = $from;
        }

        $meta = $message->getMeta();
        $mediaUrls = $meta['media_urls'] ?? [];
        if (!empty($mediaUrls)) {
            $body['type'] = 'mt_media';
            $body['parameters'] = ['media' => ['url' => $mediaUrls[0]]];
        }

        $url = $this->smsBaseUrl() . "/xms/v1/{$servicePlanId}/batches";

        $result = $this->httpPost($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Sinch SMS credentials', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300) {
            return DeliveryResult::queued($data['id'] ?? null);
        }

        return DeliveryResult::failed(
            $data['text'] ?? $data['message'] ?? "HTTP {$result['code']}",
            meta: array_filter([
                'sinch_code' => $data['code'] ?? null,
            ]),
        );
    }

    private function sendConversation(MessageInterface $message, string $channel): DeliveryResult
    {
        $projectId = $this->getChannelConfig($channel, 'project_id');
        $appId = $this->getChannelConfig($channel, 'app_id');
        $keyId = $this->getChannelConfig($channel, 'access_key_id');
        $keySecret = $this->getChannelConfig($channel, 'access_key_secret');

        if (!$projectId || !$appId || !$keyId || !$keySecret) {
            return DeliveryResult::failed(sprintf(__('Sinch %s credentials not configured', 'wp-sms'), strtoupper($channel)));
        }

        $sinchChannel = $channel === 'whatsapp' ? 'WHATSAPP' : 'RCS';
        $meta = $message->getMeta();

        $messagePayload = $this->buildConversationMessagePayload($message, $meta);

        $body = [
            'app_id'    => $appId,
            'recipient' => [
                'identified_by' => [
                    'channel_identities' => [
                        ['channel' => $sinchChannel, 'identity' => $message->getRecipient()],
                    ],
                ],
            ],
            'message'                => $messagePayload,
            'channel_priority_order' => [$sinchChannel],
        ];

        $url = $this->conversationsBaseUrl() . "/v1/projects/{$projectId}/messages:send";

        $result = $this->httpPost($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("{$keyId}:{$keySecret}"),
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Sinch Conversations API credentials', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300) {
            return DeliveryResult::queued($data['message_id'] ?? null);
        }

        return DeliveryResult::failed(
            $data['error']['message'] ?? $data['message'] ?? "HTTP {$result['code']}",
            meta: array_filter([
                'sinch_code' => $data['error']['code'] ?? null,
            ]),
        );
    }

    private function buildConversationMessagePayload(MessageInterface $message, array $meta): array
    {
        if (!empty($meta['template'])) {
            return ['template_message' => $meta['template']];
        }

        $mediaUrls = $meta['media_urls'] ?? [];
        if (!empty($mediaUrls)) {
            return [
                'media_message' => array_filter([
                    'url'      => $mediaUrls[0],
                    'thumbnail_url' => $meta['thumbnail_url'] ?? null,
                ]),
            ];
        }

        return ['text_message' => ['text' => $message->getBody()]];
    }

    public function getCredit(): ?string
    {
        // Sinch does not expose a public balance API. Return null; admins check the dashboard.
        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        // Prefer SMS API since it has a cheap, well-defined GET endpoint.
        $servicePlanId = $this->getChannelConfig('sms', 'service_plan_id');
        $apiToken = $this->getChannelConfig('sms', 'api_token');

        if ($servicePlanId && $apiToken) {
            return $this->testSmsApi($servicePlanId, $apiToken);
        }

        // Fall back to Conversations API ping for WhatsApp-only / RCS-only configs.
        foreach (['whatsapp', 'rcs'] as $channel) {
            $projectId = $this->getChannelConfig($channel, 'project_id');
            $keyId = $this->getChannelConfig($channel, 'access_key_id');
            $keySecret = $this->getChannelConfig($channel, 'access_key_secret');
            if ($projectId && $keyId && $keySecret) {
                return $this->testConversationsApi($projectId, $keyId, $keySecret);
            }
        }

        return TestConnectionResult::error(__('Configure at least one channel before testing.', 'wp-sms'));
    }

    private function testSmsApi(string $servicePlanId, string $apiToken): TestConnectionResult
    {
        $url = $this->smsBaseUrl() . "/xms/v1/{$servicePlanId}/batches?page_size=1";

        $result = $this->httpGet($url, [
            'headers' => ['Authorization' => 'Bearer ' . $apiToken],
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Service Plan ID or API Token', 'wp-sms'));
            }
            if ($result['code'] === 404) {
                return TestConnectionResult::error(__('Service plan not found in this region — check Region setting.', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Sinch');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        return TestConnectionResult::ok(__('Connected to Sinch SMS API', 'wp-sms'));
    }

    private function testConversationsApi(string $projectId, string $keyId, string $keySecret): TestConnectionResult
    {
        $url = $this->conversationsBaseUrl() . "/v1/projects/{$projectId}/apps?page_size=1";

        $result = $this->httpGet($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("{$keyId}:{$keySecret}"),
            ],
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Project ID or Access Key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Sinch Conversations');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        return TestConnectionResult::ok(__('Connected to Sinch Conversations API', 'wp-sms'));
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifySinchSignature($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $params = $request->get_json_params() ?: $request->get_params();

        // Conversations API delivery report
        if (isset($params['message_delivery_report'])) {
            return $this->parseConversationsDeliveryReport($params['message_delivery_report']);
        }

        // SMS API delivery report — type is delivery_report_sms / delivery_report_mms
        $type = $params['type'] ?? '';
        if (str_starts_with($type, 'delivery_report_')) {
            return $this->parseSmsDeliveryReport($params);
        }

        return [];
    }

    /** @return StatusUpdate[] */
    private function parseSmsDeliveryReport(array $params): array
    {
        $batchId = $params['batch_id'] ?? null;
        if (!$batchId) {
            return [];
        }

        $updates = [];
        foreach ($params['statuses'] ?? [] as $statusGroup) {
            $code = (int) ($statusGroup['code'] ?? 0);
            $statusLabel = $statusGroup['status'] ?? 'Unknown';
            $normalized = $this->normalizeSmsStatus($statusLabel);

            $recipients = $statusGroup['recipients'] ?? [null];
            foreach ($recipients as $_recipient) {
                $updates[] = new StatusUpdate(
                    providerId:   (string) $batchId,
                    status:       $normalized,
                    errorCode:    $code ? (string) $code : null,
                    errorMessage: $normalized === 'failed' ? sprintf('Sinch: %s (%d)', $statusLabel, $code) : null,
                    permanent:    $this->isPermanentSmsCode($code),
                );
            }
        }

        return $updates;
    }

    /** @return StatusUpdate[] */
    private function parseConversationsDeliveryReport(array $report): array
    {
        $messageId = $report['message_id'] ?? null;
        $rawStatus = $report['status'] ?? '';
        if (!$messageId || $rawStatus === '') {
            return [];
        }

        $normalized = match ($rawStatus) {
            'QUEUED', 'QUEUED_ON_CHANNEL'           => 'queued',
            'DELIVERED', 'READ'                     => 'delivered',
            'FAILED'                                => 'failed',
            'SWITCHING_CHANNEL'                     => 'sent',
            default                                 => strtolower($rawStatus),
        };

        $reason = $report['reason'] ?? [];
        $errorCode = isset($reason['code']) ? (string) $reason['code'] : null;

        return [new StatusUpdate(
            providerId:   (string) $messageId,
            status:       $normalized,
            errorCode:    $errorCode,
            errorMessage: $normalized === 'failed' && !empty($reason['description'])
                ? sprintf('Sinch: %s', $reason['description'])
                : null,
            permanent:    $this->isPermanentConversationsReason((string) ($reason['code'] ?? '')),
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifySinchSignature($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $params = $request->get_json_params() ?: $request->get_params();

        // Conversations API: { message: { contact_message: {...}, channel_identity: {...} } }
        if (isset($params['message']['contact_message'])) {
            return $this->parseConversationsInbound($params);
        }

        // SMS API: { type: "mo_text" | "mo_binary", from, to, body, ... }
        $type = $params['type'] ?? '';
        if (str_starts_with($type, 'mo_')) {
            return $this->parseSmsInbound($params);
        }

        return [];
    }

    /** @return InboundMessage[] */
    private function parseSmsInbound(array $params): array
    {
        $from = (string) ($params['from'] ?? '');
        if ($from === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($params['to'] ?? ''),
            body:       (string) ($params['body'] ?? ''),
            providerId: $params['id'] ?? null,
            meta:       array_filter([
                'received_at' => $params['received_at'] ?? null,
                'operator_id' => $params['operator_id'] ?? null,
            ]),
        )];
    }

    /** @return InboundMessage[] */
    private function parseConversationsInbound(array $params): array
    {
        $msg = $params['message'] ?? [];
        $contactMessage = $msg['contact_message'] ?? [];
        $channelIdentity = $msg['channel_identity'] ?? [];

        $from = (string) ($channelIdentity['identity'] ?? '');
        if ($from === '') {
            return [];
        }

        $body = $contactMessage['text_message']['text']
            ?? $contactMessage['media_message']['caption']
            ?? '';

        $mediaUrls = [];
        if (!empty($contactMessage['media_message']['url'])) {
            $mediaUrls[] = $contactMessage['media_message']['url'];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($params['app_id'] ?? ''),
            body:       (string) $body,
            providerId: $msg['id'] ?? null,
            meta:       array_filter([
                'channel'         => $channelIdentity['channel'] ?? null,
                'conversation_id' => $msg['conversation_id'] ?? null,
                'contact_id'      => $msg['contact_id'] ?? null,
                'media_urls'      => $mediaUrls ?: null,
            ]),
        )];
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        $code = $result->meta['sinch_code'] ?? null;
        if ($code === null) {
            return false;
        }
        // Conversations API reason codes for opt-out / blocked recipients.
        return in_array((string) $code, ['UNSUBSCRIBED_RECIPIENT', 'BLOCKED_BY_USER', 'POLICY_VIOLATION'], true);
    }

    // --- Internal ---

    private function smsBaseUrl(): string
    {
        $region = $this->smsRegion();
        return "https://{$region}.sms.api.sinch.com";
    }

    private function conversationsBaseUrl(): string
    {
        $region = $this->getSharedConfig('region', 'us');
        $allowed = ['us', 'eu', 'br'];
        if (!in_array($region, $allowed, true)) {
            $region = 'us';
        }
        return "https://{$region}.conversation.api.sinch.com";
    }

    private function smsRegion(): string
    {
        // SMS API supports us|eu only — fall back to us if region is br.
        $region = $this->getSharedConfig('region', 'us');
        return in_array($region, ['us', 'eu'], true) ? $region : 'us';
    }

    /**
     * Verify either SMS API (timestamp+body HMAC-SHA256) or Conversations API
     * (body.nonce.timestamp HMAC-SHA256) signature.
     *
     * SMS API: header `x-sinch-signature` with hex HMAC-SHA256 of timestamp + raw body,
     *          using callback_secret. Skipped if no callback_secret is configured.
     * Conversations API: header `x-sinch-webhook-signature` with base64 HMAC-SHA256 of
     *                    body.nonce.timestamp, using webhook_secret. Required.
     */
    private function verifySinchSignature(\WP_REST_Request $request): bool
    {
        $convSig = $request->get_header('x-sinch-webhook-signature');
        if (!empty($convSig)) {
            return $this->verifyConversationsSignature($request, $convSig);
        }

        $smsSig = $request->get_header('x-sinch-signature');
        if (!empty($smsSig)) {
            return $this->verifySmsSignature($request, $smsSig);
        }

        // No signature header — accept only if no SMS callback secret is configured
        // (i.e., the user opted out of HMAC). Conversations API webhooks always sign.
        return empty($this->getChannelConfig('sms', 'callback_secret'));
    }

    private function verifySmsSignature(\WP_REST_Request $request, string $signature): bool
    {
        $secret = $this->getChannelConfig('sms', 'callback_secret');
        if (!$secret) {
            return false;
        }

        $timestamp = $request->get_header('x-sinch-timestamp') ?? '';
        $body = $request->get_body() ?? '';

        $expected = hash_hmac('sha256', $timestamp . $body, $secret);
        return hash_equals($expected, $signature);
    }

    private function verifyConversationsSignature(\WP_REST_Request $request, string $signature): bool
    {
        $nonce = $request->get_header('x-sinch-webhook-signature-nonce') ?? '';
        $timestamp = $request->get_header('x-sinch-webhook-signature-timestamp') ?? '';
        $body = $request->get_body() ?? '';

        if ($nonce === '' || $timestamp === '') {
            return false;
        }

        // The webhook secret lives on the channel that owns the webhook. Try both
        // WhatsApp and RCS secrets — we don't know which channel's app sent it.
        foreach (['whatsapp', 'rcs'] as $channel) {
            $secret = $this->getChannelConfig($channel, 'webhook_secret');
            if (!$secret) {
                continue;
            }
            $expected = base64_encode(hash_hmac('sha256', $body . '.' . $nonce . '.' . $timestamp, $secret, true));
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }
        return false;
    }

    private function normalizeSmsStatus(string $status): string
    {
        return match ($status) {
            'Queued', 'Dispatched'                                 => 'queued',
            'Delivered'                                            => 'delivered',
            'Aborted', 'Rejected', 'Failed', 'Expired', 'Cancelled' => 'failed',
            default                                                 => strtolower($status),
        };
    }

    private function isPermanentSmsCode(int $code): bool
    {
        // Sinch SMS DLR codes that indicate permanent failure (per their reference table).
        return in_array($code, [
            403, // Aborted (subscriber not found)
            408, // Rejected
            420, // Internal expired (no further attempts)
        ], true);
    }

    private function isPermanentConversationsReason(string $code): bool
    {
        return in_array($code, [
            'INVALID_RECIPIENT',
            'UNSUBSCRIBED_RECIPIENT',
            'BLOCKED_BY_USER',
            'NO_CHANNELS_LEFT',
        ], true);
    }
}

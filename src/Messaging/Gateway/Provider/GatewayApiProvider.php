<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

class GatewayApiProvider extends AbstractProvider implements SupportsStatusCallback
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    /** Account / balance lives on the legacy domain regardless of messaging region. */
    private const ACCOUNT_BASE = 'https://gatewayapi.com';

    public function getId(): string
    {
        return 'gatewayapi';
    }

    public function getSupportedChannels(): array
    {
        // Both channels send to the same transport-agnostic endpoint;
        // GatewayAPI auto-upgrades to RCS for capable recipients and
        // falls back to SMS otherwise. Webhook event_type reports which
        // transport actually delivered.
        return ['sms', 'rcs'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_token' => [
                    'type'        => 'secret',
                    'label'       => __('API Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generate at gatewayapi.com → API → API Tokens.', 'wp-sms'),
                ],
                'region' => [
                    'type'    => 'select',
                    'label'   => __('Region', 'wp-sms'),
                    'options' => [
                        ['value' => 'com', 'label' => __('Global (messaging.gatewayapi.com)', 'wp-sms')],
                        ['value' => 'eu',  'label' => __('EU (messaging.gatewayapi.eu)', 'wp-sms')],
                    ],
                    'default' => 'com',
                ],
                'webhook_secret' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Secret', 'wp-sms'),
                    'description' => __('Set the same secret on each webhook in the GatewayAPI dashboard. Required for HMAC signature validation.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'YourBrand',
                        'description' => __('Up to 11 alphanumeric chars or 15 digits.', 'wp-sms'),
                    ],
                    'priority' => [
                        'type'    => 'select',
                        'label'   => __('Default priority', 'wp-sms'),
                        'options' => [
                            ['value' => 'normal', 'label' => __('Normal', 'wp-sms')],
                            ['value' => 'urgent', 'label' => __('Urgent (transactional, may cost more)', 'wp-sms')],
                        ],
                        'default' => 'normal',
                    ],
                ],
                'rcs' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('RCS Sender / Brand', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Brand name shown on RCS-capable handsets. Falls back to SMS automatically when the recipient is not RCS-capable.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiToken = $this->getSharedConfig('api_token');
        if (!$apiToken) {
            return DeliveryResult::failed(__('GatewayAPI API Token is not configured', 'wp-sms'));
        }

        $channel = $message->getChannel();
        $sender = $this->getChannelConfig($channel, 'sender');
        if (!$sender) {
            return DeliveryResult::failed(__('GatewayAPI Sender is not configured for this channel', 'wp-sms'));
        }

        $recipient = (int) preg_replace('/\D+/', '', $message->getRecipient());
        if ($recipient <= 1000) {
            return DeliveryResult::failed(__('Invalid recipient phone number', 'wp-sms'));
        }

        $body = [
            'sender'    => $sender,
            'recipient' => $recipient,
            'message'   => $message->getBody(),
        ];

        // Round-trip a client reference so the webhook can match the message
        // back without depending on msg_id alone.
        $reference = $message->getFlowExecutionId() ?? $message->getCampaignId();
        if ($reference !== null && $reference !== '') {
            $body['reference'] = (string) $reference;
        }

        if ($channel === 'sms') {
            $priority = (string) $this->getChannelConfig('sms', 'priority', 'normal');
            if ($priority === 'urgent') {
                $body['priority'] = 'urgent';
            }
        }

        $result = $this->httpPost($this->messagingBaseUrl() . '/mobile/single', [
            'headers' => [
                'Authorization' => 'Token ' . $apiToken,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true) ?: [];

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid GatewayAPI API Token', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300) {
            return DeliveryResult::queued((string) ($data['msg_id'] ?? ''));
        }

        return DeliveryResult::failed(
            $this->extractErrorMessage($data, $result['code']),
            meta: array_filter([
                'gatewayapi_http_code' => $result['code'] ?: null,
            ]),
        );
    }

    public function getCredit(): ?string
    {
        $apiToken = $this->getSharedConfig('api_token');
        if (!$apiToken) {
            return null;
        }

        $result = $this->httpGet(self::ACCOUNT_BASE . '/rest/me', [
            'headers' => $this->authHeaders($apiToken),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }
        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['credit'])) {
            return null;
        }

        $currency = isset($data['currency']) ? ' ' . (string) $data['currency'] : '';
        return ((string) $data['credit']) . $currency;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiToken = $this->getSharedConfig('api_token');
        if (!$apiToken) {
            return TestConnectionResult::error(__('API Token is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::ACCOUNT_BASE . '/rest/me', [
            'headers' => $this->authHeaders($apiToken),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid API Token', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'GatewayAPI');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $credit = $data['credit'] ?? 'N/A';
        $currency = $data['currency'] ?? '';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %1$s %2$s', 'wp-sms'), $credit, $currency),
            ['balance' => $credit, 'currency' => $currency],
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        $secret = (string) $this->getSharedConfig('webhook_secret', '');
        if ($secret === '') {
            // Reject by default when no secret is configured. The GatewayAPI
            // dashboard requires a per-webhook secret; an unconfigured plugin
            // shouldn't accept any signed payload.
            return false;
        }

        $header = (string) ($request->get_header('signature') ?? '');
        if (!str_starts_with($header, 'v1=')) {
            return false;
        }
        $provided = substr($header, 3);

        $body = (string) ($request->get_body() ?? '');
        $expected = hash_hmac('sha256', $body, $secret);

        return hash_equals($expected, $provided);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $payload = json_decode((string) ($request->get_body() ?? ''), true);
        if (!is_array($payload)) {
            return [];
        }

        $event = $payload['event'] ?? [];
        $type  = (string) ($payload['event_type'] ?? '');

        $msgId = (string) ($event['msg_id'] ?? '');
        $rawStatus = (string) ($event['status'] ?? '');
        if ($msgId === '' || $rawStatus === '') {
            return [];
        }

        $isRcs = $type === 'message.status.rcs';
        $normalized = $this->mapStatus($rawStatus, $isRcs);

        $errorPayload = $event['error'] ?? null;
        $errorCode = is_array($errorPayload) ? ($errorPayload['hex_code'] ?? null) : null;
        $errorMessage = is_array($errorPayload) ? ($errorPayload['details'] ?? null) : null;

        return [new StatusUpdate(
            providerId:   $msgId,
            status:       $normalized,
            errorCode:    $errorCode !== null ? (string) $errorCode : null,
            errorMessage: $errorMessage !== null ? (string) $errorMessage : null,
            permanent:    $this->isPermanentStatus($rawStatus),
        )];
    }

    // --- Internal ---

    private function messagingBaseUrl(): string
    {
        $region = (string) $this->getSharedConfig('region', 'com');
        return $region === 'eu'
            ? 'https://messaging.gatewayapi.eu'
            : 'https://messaging.gatewayapi.com';
    }

    private function authHeaders(string $apiToken): array
    {
        return [
            'Authorization' => 'Token ' . $apiToken,
            'Accept'        => 'application/json',
        ];
    }

    private function mapStatus(string $rawStatus, bool $isRcs): string
    {
        // SMSStatus enum: ENROUTE, DELIVERED, EXPIRED, DELETED, UNDELIVERABLE,
        //                 ACCEPTED, UNKNOWN, REJECTED.
        // RCSStatus enum: DELIVERED, READ, ENROUTE, EXPIRED.
        // READ is RCS-only and represents an explicit confirmation past
        // delivery — collapse to 'delivered' so WSMS treats it as success.
        return match ($rawStatus) {
            'ACCEPTED', 'ENROUTE'                      => 'sent',
            'DELIVERED', 'READ'                        => 'delivered',
            'EXPIRED', 'UNDELIVERABLE', 'REJECTED',
            'DELETED'                                  => 'failed',
            default                                    => 'unknown',
        };
    }

    private function isPermanentStatus(string $rawStatus): bool
    {
        return in_array($rawStatus, ['REJECTED', 'UNDELIVERABLE', 'DELETED'], true);
    }

    private function extractErrorMessage(array $data, int $httpCode): string
    {
        // 422 ValidationError: {detail: [{loc, msg, type}, ...]}
        if (isset($data['detail']) && is_array($data['detail'])) {
            $first = $data['detail'][0] ?? null;
            if (is_array($first) && !empty($first['msg'])) {
                $location = is_array($first['loc'] ?? null) ? implode('.', $first['loc']) : '';
                return $location !== '' ? sprintf('%s: %s', $location, $first['msg']) : (string) $first['msg'];
            }
        }
        if (!empty($data['message'])) {
            return (string) $data['message'];
        }
        if (!empty($data['detail']) && is_string($data['detail'])) {
            return (string) $data['detail'];
        }
        return sprintf('HTTP %d', $httpCode);
    }
}

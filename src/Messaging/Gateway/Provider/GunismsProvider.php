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
 * GuniSMS — Australian SMS gateway with onshore routing for SMS and MMS.
 *
 * Bearer-token auth against api.gunisms.com.au; status / inbound webhooks are
 * unsigned upstream, so callbacks are gated by a shared-secret query parameter
 * which the admin configures in the gateway settings and pastes into the
 * GuniSMS dashboard webhook URL.
 */
class GunismsProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.gunisms.com.au/api/v1';

    public function getId(): string
    {
        return 'gunisms';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'gateway_token' => [
                    'type'        => 'secret',
                    'label'       => __('API Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generate at app.gunisms.com.au → Integrations → Create APP Token.', 'wp-sms'),
                ],
                'webhook_secret' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Secret', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Optional shared secret. When set, GuniSMS webhook URLs must include ?secret=… and requests without a matching value are rejected. Leave blank to accept any inbound callback.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => '+614xxxxxxxx',
                        'description' => __('Sender phone number or alphanumeric ID registered on your GuniSMS account.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if ($message->getChannel() !== 'sms') {
            return DeliveryResult::failed(sprintf(__('GuniSMS does not support channel %s', 'wp-sms'), $message->getChannel()));
        }

        $token = $this->getSharedConfig('gateway_token');
        $from = $this->getChannelConfig('sms', 'from');
        if (!$token || !$from) {
            return DeliveryResult::failed(__('GuniSMS credentials not configured', 'wp-sms'));
        }

        $body = [
            'message'  => $message->getBody(),
            'contacts' => [ltrim((string) $message->getRecipient(), '+')],
            'sender'   => $from,
        ];

        $endpoint = '/gateway';
        $mediaUrls = $message->getMeta()['media_urls'] ?? [];
        if (!empty($mediaUrls)) {
            $body['media'] = $mediaUrls[0];
            $endpoint = '/gatewaymms';
        }

        $result = $this->httpPost(self::API_BASE . $endpoint, [
            'headers' => $this->buildHeaders($token),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        $data = is_array($data) ? $data : [];

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid GuniSMS credentials', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300 && ($data['status'] ?? false) === true) {
            $providerId = $data['data']['id'] ?? $data['_id'] ?? null;
            return DeliveryResult::queued($providerId !== null ? (string) $providerId : null);
        }

        return DeliveryResult::failed($data['message'] ?? "HTTP {$result['code']}");
    }

    public function getCredit(): ?string
    {
        $token = $this->getSharedConfig('gateway_token');
        if (!$token) {
            return null;
        }

        $result = $this->httpPost(self::API_BASE . '/user/balance', [
            'headers' => $this->buildHeaders($token),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || ($data['status'] ?? false) !== true) {
            return null;
        }

        // v7 returned $response->balance (top-level); the v8 ReDoc spec hints at
        // a nested data.balance shape. Try both so either response works.
        $balance = $data['balance'] ?? $data['data']['balance'] ?? null;
        return $balance !== null ? (string) $balance : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $token = $this->getSharedConfig('gateway_token');
        if (!$token) {
            return TestConnectionResult::error(__('API Token is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/user/token/verify', [
            'headers' => $this->buildHeaders($token),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid GuniSMS API token', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'GuniSMS');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        return TestConnectionResult::ok(__('Connected to GuniSMS API', 'wp-sms'));
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyWebhookSecret($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $params = $request->get_json_params() ?: $request->get_params();
        $providerId = $params['_id'] ?? null;
        $rawStatus = $params['status'] ?? '';

        if (!$providerId || $rawStatus === '') {
            return [];
        }

        $normalized = $this->normalizeStatus((string) $rawStatus);
        $isFailed = $normalized === 'failed';

        return [new StatusUpdate(
            providerId:   (string) $providerId,
            status:       $normalized,
            errorCode:    null,
            errorMessage: $isFailed ? ($params['message'] ?? null) : null,
            permanent:    $isFailed,
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyWebhookSecret($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $params = $request->get_json_params() ?: $request->get_params();

        if (($params['type'] ?? '') !== 'receive') {
            return [];
        }

        $from = (string) ($params['sender'] ?? '');
        if ($from === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($params['receiver'] ?? ''),
            body:       (string) ($params['message'] ?? ''),
            providerId: isset($params['_id']) ? (string) $params['_id'] : null,
        )];
    }

    // --- Internal ---

    private function buildHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];
    }

    private function verifyWebhookSecret(\WP_REST_Request $request): bool
    {
        $configured = (string) ($this->getSharedConfig('webhook_secret') ?? '');
        if ($configured === '') {
            return true;
        }
        $supplied = (string) ($request->get_param('secret') ?? '');
        return $supplied !== '' && hash_equals($configured, $supplied);
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower($status)) {
            'delivered'                     => 'delivered',
            'failed', 'rejected', 'expired' => 'failed',
            'queued', 'pending'             => 'queued',
            'sent'                          => 'sent',
            default                         => strtolower($status),
        };
    }
}

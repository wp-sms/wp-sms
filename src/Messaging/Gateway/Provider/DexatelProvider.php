<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\SupportsInboundMessage;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

/**
 * Dexatel — global multi-channel messaging.
 *
 * Unified REST API: every channel (SMS, WhatsApp, Viber, RCS) routes through
 * POST /v1/messages with a `channel` field. Auth is X-Dexatel-Key on every
 * request. Webhooks (DLR + inbound) are HMAC-SHA256 of the raw request body
 * in X-Dexatel-Signature, signed with a shared secret the operator sets in
 * the Dexatel dashboard.
 *
 * TODO(verify): Dexatel exposes POST /v1/verifications + GET /v1/verifications?code=...&phone=...
 * for OTP-as-a-Service. Defer until WSMS adds a SupportsVerify interface.
 */
class DexatelProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsDynamicOptions
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.dexatel.com';

    public function getId(): string
    {
        return 'dexatel';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp', 'viber', 'rcs'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('From Dashboard → Settings → API Keys. Sent as X-Dexatel-Key on every request.', 'wp-sms'),
                ],
                'webhook_secret' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Secret', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Shared HMAC secret you set when registering DLR/inbound webhooks. Leave blank to skip signature verification.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Verified sender ID or phone number from Dashboard → Senders. Dropdown auto-populates when an API key is set.', 'wp-sms'),
                        'placeholder' => '+15551234567',
                        'dynamic'     => true,
                    ],
                ],
                'whatsapp' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('WhatsApp Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Meta-approved WhatsApp Business sender registered in your Dexatel account.', 'wp-sms'),
                    ],
                ],
                'viber' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Viber Sender Name', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Approved Viber sender name from Dashboard → Senders.', 'wp-sms'),
                    ],
                ],
                'rcs' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('RCS Agent ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Verified RCS agent ID from Dashboard → Senders.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $channel = $message->getChannel();
        if (!in_array($channel, $this->getSupportedChannels(), true)) {
            return DeliveryResult::failed(sprintf(__('Dexatel does not support channel %s', 'wp-sms'), $channel));
        }

        $apiKey = $this->getSharedConfig('api_key');
        $from   = $this->getChannelConfig($channel, 'from');

        if (!$apiKey || !$from) {
            return DeliveryResult::failed(__('Dexatel credentials not configured', 'wp-sms'));
        }

        $body = ['data' => [
            'from'    => $from,
            'to'      => [$message->getRecipient()],
            'text'    => $message->getBody(),
            'channel' => $channel,
        ]];

        $result = $this->httpPost(self::API_BASE . '/v1/messages', [
            'headers' => [
                'X-Dexatel-Key' => $apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Dexatel API key', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300) {
            $messageId = $data['data']['id'] ?? null;
            return DeliveryResult::queued(is_string($messageId) ? $messageId : null);
        }

        $errorMessage = $data['errors'][0]['message'] ?? $data['message'] ?? sprintf('HTTP %d', $result['code']);
        $errorCode    = $data['errors'][0]['code'] ?? null;

        return DeliveryResult::failed(
            sprintf('Dexatel: %s', $errorMessage),
            meta: array_filter(['dexatel_error' => $errorCode]),
        );
    }

    public function getCredit(): ?string
    {
        // Dexatel's REST API does not expose balance to API-key callers.
        // /v1/accounts has no balance field; /v1/payments and /v1/payments/balance
        // exist but return 403 for API-key auth. Operators check the balance in
        // the Dexatel dashboard.
        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('Enter your Dexatel API key first.', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/v1/senders?limit=1', [
            'headers' => ['X-Dexatel-Key' => $apiKey, 'Accept' => 'application/json'],
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Dexatel API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Dexatel');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        return TestConnectionResult::ok(__('Connected to Dexatel', 'wp-sms'));
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyDexatelSignature($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $payload = $request->get_json_params() ?: $request->get_params();
        $data = $payload['data'] ?? [];

        $event     = (string) ($data['event'] ?? '');
        $messageId = (string) ($data['message_id'] ?? '');

        if ($event === '' || $messageId === '' || $event === 'message') {
            // event="message" is inbound, not a delivery report.
            return [];
        }

        $normalized = match ($event) {
            'delivered'             => 'delivered',
            'submitted', 'pending'  => 'queued',
            'dropped'               => 'failed',
            default                 => strtolower($event),
        };

        return [new StatusUpdate(
            providerId:   $messageId,
            status:       $normalized,
            errorCode:    null,
            errorMessage: $normalized === 'failed' ? sprintf('Dexatel: %s', $event) : null,
            permanent:    $event === 'dropped',
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyDexatelSignature($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $payload = $request->get_json_params() ?: $request->get_params();
        $data = $payload['data'] ?? [];

        $from = (string) ($data['from'] ?? '');
        if ($from === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($data['to'] ?? ''),
            body:       (string) ($data['text'] ?? ''),
            providerId: isset($data['message_id']) ? (string) $data['message_id'] : null,
            meta:       array_filter([
                'channel'   => $data['channel'] ?? null,
                'timestamp' => $data['timestamp'] ?? null,
            ]),
        )];
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'from' || $section !== 'sms') {
            return [];
        }

        return $this->withConfig($config, function () {
            $apiKey = $this->getSharedConfig('api_key');
            if (!$apiKey) {
                return [];
            }
            return $this->fetchSenders($apiKey);
        });
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function fetchSenders(string $apiKey): array
    {
        $data = $this->fetchJsonOrFail(self::API_BASE . '/v1/senders', [
            'headers' => ['X-Dexatel-Key' => $apiKey, 'Accept' => 'application/json'],
        ]);

        $options = [];
        foreach ($data['data'] ?? [] as $sender) {
            $value = (string) ($sender['phone'] ?? $sender['name'] ?? '');
            if ($value === '') {
                continue;
            }
            $displayName = (string) ($sender['display_name'] ?? $sender['name'] ?? '');
            $channel     = (string) ($sender['channel'] ?? '');

            $detail = array_filter([$channel ?: null, $displayName && $displayName !== $value ? $displayName : null]);
            $label  = $detail ? $value . ' (' . implode(' — ', $detail) . ')' : $value;

            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    // --- Internal ---

    /**
     * Verify HMAC-SHA256 of the raw request body against X-Dexatel-Signature.
     * If no webhook_secret is configured, accept (operator opted out of signing).
     */
    private function verifyDexatelSignature(\WP_REST_Request $request): bool
    {
        $secret = $this->getSharedConfig('webhook_secret');
        if (!$secret) {
            return true;
        }

        $signature = $request->get_header('x-dexatel-signature') ?? '';
        if ($signature === '') {
            return false;
        }

        $body = $request->get_body() ?? '';
        $expected = hash_hmac('sha256', $body, $secret);

        return hash_equals($expected, $signature);
    }
}

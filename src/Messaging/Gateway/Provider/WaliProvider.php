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
 * Wali — WhatsApp Business gateway via the wali.chat session-based bridge.
 *
 * Not Meta WABA Cloud API: Wali brokers messages through a logged-in WhatsApp
 * Web/Business session (multi-device QR / pairing-code), so freeform text works
 * outside the 24-hour template window and there are no Meta-approved templates
 * to register. This is also why there is no /verify, /balance, or /templates
 * endpoint to wire to.
 *
 * Auth: `Authorization: Bearer <api_key>` (canonical per walichat/n8n-walichat).
 * The legacy v7 plugin used a `Token: <key>` header; both still work, Bearer is
 * what wali themselves use.
 *
 * Webhooks are NOT signed. We require a shared-secret query token on the
 * registered URL (`?token=…`) so the plugin can authenticate inbound webhook
 * deliveries — the same pattern used for BulkGate.
 */
class WaliProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsDynamicOptions
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.wali.chat/v1';

    public function getId(): string
    {
        return 'wali';
    }

    public function getSupportedChannels(): array
    {
        return ['whatsapp'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generate an API key at app.wali.chat → Developers → API keys.', 'wp-sms'),
                ],
                'callback_token' => [
                    'type'        => 'secret',
                    'label'       => __('Callback Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Shared secret appended to the webhook URL as ?token=… so the plugin can verify delivery reports and inbound messages. Required to enable two-way messaging and DLR — Wali does not sign webhooks.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'whatsapp' => [
                    'device_id' => [
                        'type'        => 'select',
                        'label'       => __('WhatsApp Device', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Connected WhatsApp number used to send messages. Connect a device first at app.wali.chat — the dropdown populates from your account.', 'wp-sms'),
                        'dynamic'     => true,
                        'options'     => [],
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        // TODO(verify): Wali does not expose a /verify endpoint AND WSMS does not
        // yet ship SupportsVerify; OTPs go through plain WhatsApp text body.

        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('Wali API key not configured', 'wp-sms'));
        }

        $deviceId = $this->getChannelConfig('whatsapp', 'device_id');
        if (!$deviceId) {
            return DeliveryResult::failed(__('Wali WhatsApp Device is not configured', 'wp-sms'));
        }

        $body = [
            'phone'   => $message->getRecipient(),
            'message' => $message->getBody(),
            'device'  => $deviceId,
        ];

        $meta = $message->getMeta();
        $mediaUrls = $meta['media_urls'] ?? [];
        if (!empty($mediaUrls)) {
            // Wali's send endpoint accepts a single media attachment per message,
            // referenced by file id from the upload step.
            $fileId = $this->uploadMedia($apiKey, (string) $mediaUrls[0]);
            if ($fileId === null) {
                return DeliveryResult::failed(__('Wali media upload failed', 'wp-sms'));
            }
            $body['media'] = ['file' => $fileId];
        }

        $result = $this->httpPost(self::API_BASE . '/messages', [
            'headers' => $this->authHeaders($apiKey),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Wali API key', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300 && is_array($data)) {
            return DeliveryResult::queued($data['id'] ?? null);
        }

        return DeliveryResult::failed(
            $this->describeError($data, $result['code']),
            meta: array_filter([
                'wali_code'  => isset($data['code']) ? (string) $data['code'] : null,
                'wali_error' => is_array($data) && isset($data['error']) && is_string($data['error']) ? $data['error'] : null,
            ]),
        );
    }

    public function getCredit(): ?string
    {
        // Wali does not expose a public balance/account endpoint. Surface null
        // so the dashboard hides the balance pill instead of inventing a value.
        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/devices?size=1', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Wali API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Wali');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $devices = $this->extractDevices($data);
        $count = count($devices);

        if ($count === 0) {
            return TestConnectionResult::ok(
                __('Connected — no WhatsApp devices linked yet. Connect one at app.wali.chat to start sending.', 'wp-sms'),
                ['device_count' => 0],
            );
        }

        return TestConnectionResult::ok(
            sprintf(
                /* translators: %d: number of WhatsApp devices linked to the Wali account */
                __('Connected — %d WhatsApp device(s) linked', 'wp-sms'),
                $count,
            ),
            ['device_count' => $count],
        );
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'device_id' || $section !== 'whatsapp') {
            return [];
        }

        return $this->withConfig($config, function () {
            $apiKey = $this->getSharedConfig('api_key');
            if (!$apiKey) {
                throw new \RuntimeException(__('API Key is required', 'wp-sms'));
            }

            $data = $this->fetchJsonOrFail(self::API_BASE . '/devices?size=100', [
                'headers' => $this->authHeaders($apiKey),
            ]);

            $options = [];
            foreach ($this->extractDevices($data) as $device) {
                if (($device['status'] ?? '') !== 'operative') {
                    continue;
                }
                $id = $device['id'] ?? '';
                if (!$id) {
                    continue;
                }
                $alias = $device['alias'] ?? '';
                $phone = $device['phone'] ?? '';
                $label = $alias && $phone
                    ? sprintf('%s (%s)', $alias, $phone)
                    : ($alias ?: $phone ?: (string) $id);

                $options[] = ['value' => (string) $id, 'label' => $label];
            }

            return $options;
        });
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $event = $this->extractEvent($request);
        if ($event === null) {
            return [];
        }

        [$eventName, $data] = $event;
        if (!str_starts_with($eventName, 'message:out:')) {
            return [];
        }

        $providerId = $this->stringOrNull($data['id'] ?? null);
        if ($providerId === null) {
            return [];
        }

        $deliveryStatus = (string) ($data['deliveryStatus'] ?? '');

        $normalized = match ($eventName) {
            'message:out:sent'   => 'sent',
            'message:out:ack'    => in_array($deliveryStatus, ['delivered', 'read'], true) ? 'delivered' : 'sent',
            'message:out:failed' => 'failed',
            default              => null,
        };

        if ($normalized === null) {
            return [];
        }

        $errorReason = $this->stringOrNull($data['failureReason'] ?? $data['error'] ?? null);

        return [new StatusUpdate(
            providerId:   $providerId,
            status:       $normalized,
            errorCode:    $normalized === 'failed' ? ($this->stringOrNull($data['failureCode'] ?? null) ?: 'failed') : null,
            errorMessage: $normalized === 'failed' ? ($errorReason !== null ? sprintf('Wali: %s', $errorReason) : __('Wali: send failed', 'wp-sms')) : null,
            permanent:    $normalized === 'failed',
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $event = $this->extractEvent($request);
        if ($event === null) {
            return [];
        }

        [$eventName, $data] = $event;
        if ($eventName !== 'message:in:new') {
            return [];
        }

        $from = $this->phoneFrom($data['from'] ?? null) ?? $this->stringOrNull($data['phone'] ?? null);
        if ($from === null || $from === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($this->phoneFrom($data['to'] ?? null) ?? ''),
            body:       (string) ($data['body'] ?? $data['message'] ?? ''),
            providerId: $this->stringOrNull($data['id'] ?? null),
            meta:       array_filter([
                'type'    => $this->stringOrNull($data['type'] ?? null),
                'device'  => $this->stringOrNull($data['device'] ?? null),
                'chat_id' => $this->stringOrNull($data['chat'] ?? null),
            ]),
        )];
    }

    // --- Internal ---

    /** @return array<string, string> */
    private function authHeaders(string $apiKey): array
    {
        return [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    /**
     * Upload a media URL to /files. Returns the file id, or null on failure.
     */
    private function uploadMedia(string $apiKey, string $url): ?string
    {
        $result = $this->httpPost(self::API_BASE . '/files', [
            'headers' => $this->authHeaders($apiKey),
            'body'    => wp_json_encode(['url' => $url]),
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

        // Wali returns either a single object {id: ...} or an array — match the
        // legacy v7 behaviour and accept both shapes.
        if (isset($data['id'])) {
            return (string) $data['id'];
        }
        if (array_is_list($data) && isset($data[0]['id'])) {
            return (string) $data[0]['id'];
        }

        return null;
    }

    private function describeError(mixed $data, int $httpCode): string
    {
        if (is_array($data)) {
            // Validation errors: {errors: [{path, message}]} (matches v7 parser).
            if (!empty($data['errors']) && is_array($data['errors'])) {
                $first = $data['errors'][0] ?? [];
                if (is_array($first)) {
                    $path = isset($first['path']) ? (string) $first['path'] : '';
                    $msg  = isset($first['message']) ? (string) $first['message'] : '';
                    if ($path && $msg) {
                        return sprintf('%s: %s', $path, $msg);
                    }
                    if ($msg) {
                        return $msg;
                    }
                }
            }
            // Request-layer errors from the n8n SDK shape: {error, code, message}.
            if (!empty($data['message']) && is_string($data['message'])) {
                return $data['message'];
            }
            if (!empty($data['error']) && is_string($data['error'])) {
                return $data['error'];
            }
        }
        return sprintf('HTTP %d', $httpCode);
    }

    private function verifyToken(\WP_REST_Request $request): bool
    {
        $expected = (string) $this->getSharedConfig('callback_token', '');
        if ($expected === '') {
            return false;
        }
        $given = (string) ($request->get_param('token') ?? '');
        if ($given === '') {
            return false;
        }
        return hash_equals($expected, $given);
    }

    /**
     * Pull the `event` name and `data` payload out of a webhook request, tolerating
     * the two shapes Wali uses: top-level `{event, data: {...}}` and a flat object
     * where `event` is a sibling of message fields.
     *
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    private function extractEvent(\WP_REST_Request $request): ?array
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            return null;
        }

        $eventName = $this->stringOrNull($payload['event'] ?? null);
        if ($eventName === null) {
            return null;
        }

        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            // Flat shape — strip the event key, treat the rest as data.
            $data = $payload;
            unset($data['event']);
        }

        return [$eventName, $data];
    }

    /**
     * Wali's device list endpoint returns either a top-level array or a wrapped
     * `{data: [...]}` object depending on context. Tolerate both.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractDevices(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }
        if (array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }
        if (isset($data['data']) && is_array($data['data']) && array_is_list($data['data'])) {
            return array_values(array_filter($data['data'], 'is_array'));
        }
        return [];
    }

    /**
     * Wali's `from` and `to` fields are either a plain phone string or an object
     * `{phone, name}`. Normalize to the bare phone.
     */
    private function phoneFrom(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }
        if (is_array($value) && isset($value['phone']) && is_string($value['phone'])) {
            return $value['phone'];
        }
        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return null;
    }
}

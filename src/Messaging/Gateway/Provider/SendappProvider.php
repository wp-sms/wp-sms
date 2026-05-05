<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Bootstrap;
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
 * SendApp SMS gateway (sms.sendapp.live) — sends via the user's own Android phone
 * running the SendApp SMS Gateway app, paired with their account on the panel.
 *
 * Webhook quirk: SendApp's panel accepts a single webhook URL that delivers
 * BOTH delivery-status updates AND received SMS in the same payload shape.
 * Status updates and inbound messages are partitioned in parseStatusCallback
 * by the `status` field — `Received` rows are routed through OptOutManager
 * directly so the unified webhook still drives opt-out + auto-reply.
 */
class SendappProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsOptOutDetection
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://sms.sendapp.live/services/send.php';

    public function getId(): string
    {
        return 'sendapp';
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
                    'description' => __('Your SendApp API Key from sms.sendapp.live (My Profile > API Key).', 'wp-sms'),
                ],
                'device_id' => [
                    'type'        => 'string',
                    'label'       => __('Device (optional)', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Restrict sending to a specific paired device + SIM slot, e.g. "123|0". Leave blank to let SendApp pick.', 'wp-sms'),
                    'placeholder' => '123|0',
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('SendApp API Key is not configured', 'wp-sms'));
        }

        $payload = [
            'number'  => $message->getRecipient(),
            'message' => $message->getBody(),
            'key'     => $apiKey,
        ];

        $deviceId = $this->getSharedConfig('device_id');
        if (!empty($deviceId)) {
            $payload['devices'] = $deviceId;
        }

        $result = $this->httpPost(self::API_BASE, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query($payload),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid SendApp API Key', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] < 200 || $result['code'] >= 300) {
            $error = is_array($data) ? ($data['message'] ?? $data['error'] ?? null) : null;
            return DeliveryResult::failed($error ?: sprintf('HTTP %d', $result['code']));
        }

        if (!is_array($data)) {
            return DeliveryResult::failed(__('Invalid response from SendApp', 'wp-sms'));
        }

        // SendApp returns { success: true, data: { messageID: ... } } or { success: false, message: ... }
        $success = $data['success'] ?? null;
        if ($success === false || (isset($data['error']) && $data['error'])) {
            $error = $data['message'] ?? $data['error'] ?? __('SendApp did not accept the message', 'wp-sms');
            return DeliveryResult::failed(is_string($error) ? $error : __('SendApp send failed', 'wp-sms'));
        }

        $providerId = $data['data']['messageID']
            ?? $data['data']['id']
            ?? $data['messageID']
            ?? $data['id']
            ?? null;

        return DeliveryResult::sent(providerId: $providerId !== null ? (string) $providerId : null);
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpPost(self::API_BASE, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query(['key' => $apiKey]),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return null;
        }

        $credits = $data['data']['credits'] ?? $data['credits'] ?? null;
        return $credits !== null ? (string) $credits : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query(['key' => $apiKey]),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid API Key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'SendApp');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $credits = $data['data']['credits'] ?? $data['credits'] ?? null;
        if ($credits === null) {
            return TestConnectionResult::error(__('Invalid API Key', 'wp-sms'));
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credits: %s', 'wp-sms'), $credits),
            ['credits' => $credits],
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
        return $this->verifySignature($request);
    }

    /**
     * Parse SendApp's webhook payload. The same payload shape carries delivery
     * status AND received SMS, discriminated by the `status` field. Non-Received
     * rows return as StatusUpdate[]; Received rows are dispatched through
     * OptOutManager so the unified-webhook detail stays invisible to the controller.
     *
     * @return StatusUpdate[]
     */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $messages = $this->decodeMessages($request);
        if ($messages === []) {
            return [];
        }

        $statusUpdates = [];
        $inbound = [];

        foreach ($messages as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rawStatus = (string) ($row['status'] ?? '');

            if ($rawStatus === 'Received') {
                $msg = $this->buildInboundMessage($row);
                if ($msg !== null) {
                    $inbound[] = $msg;
                }
                continue;
            }

            $update = $this->buildStatusUpdate($row, $rawStatus);
            if ($update !== null) {
                $statusUpdates[] = $update;
            }
        }

        if ($inbound !== []) {
            $optOutManager = $this->resolveOptOutManager();
            if ($optOutManager !== null) {
                foreach ($inbound as $msg) {
                    $optOutManager->processInboundMessage($msg, $this->getId());
                }
            }
        }

        return $statusUpdates;
    }

    // --- SupportsInboundMessage ---
    //
    // The plugin's REST layer exposes /callbacks/sendapp/inbound for completeness
    // — SendApp's current panel only accepts one webhook URL (handled via
    // parseStatusCallback above), but if a future version supports two URLs
    // these methods filter to Received-only rows.

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url(
            'callbacks/' . $this->getId() . '/inbound',
            ['token' => $this->callbackToken()],
        );
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifySignature($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $messages = $this->decodeMessages($request);
        if ($messages === []) {
            return [];
        }

        $out = [];
        foreach ($messages as $row) {
            if (!is_array($row) || ($row['status'] ?? null) !== 'Received') {
                continue;
            }
            $msg = $this->buildInboundMessage($row);
            if ($msg !== null) {
                $out[] = $msg;
            }
        }

        return $out;
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        // SendApp's docs don't enumerate carrier-level opt-out errors; the
        // inbound-keyword path (handled via OptOutManager + KeywordMatcher) is
        // the primary opt-out detection mechanism for this provider.
        return false;
    }

    // --- Internal ---

    /**
     * Verify the X-SG-Signature header. SendApp signs the JSON-encoded
     * `messages` form parameter with HMAC-SHA256 using the API key, base64-encoded.
     */
    private function verifySignature(\WP_REST_Request $request): bool
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return false;
        }

        $signature = (string) ($request->get_header('x-sg-signature') ?? '');
        if ($signature === '') {
            return false;
        }

        $messagesParam = (string) ($request->get_param('messages') ?? '');
        if ($messagesParam === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $messagesParam, $apiKey, true));

        return hash_equals($expected, $signature);
    }

    /**
     * Decode the `messages` form parameter into an array of message rows.
     *
     * @return array<int, mixed>
     */
    private function decodeMessages(\WP_REST_Request $request): array
    {
        $raw = $request->get_param('messages');
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function buildStatusUpdate(array $row, string $rawStatus): ?StatusUpdate
    {
        $providerId = $row['ID'] ?? null;
        if ($providerId === null || $providerId === '') {
            return null;
        }

        $normalized = match ($rawStatus) {
            'Sent'      => 'sent',
            'Delivered' => 'delivered',
            'Pending'   => 'queued',
            'Failed'    => 'failed',
            default     => null,
        };

        if ($normalized === null) {
            return null;
        }

        return new StatusUpdate(
            providerId:   (string) $providerId,
            status:       $normalized,
            errorMessage: $normalized === 'failed' ? sprintf('SendApp DLR: %s', $rawStatus) : null,
        );
    }

    private function buildInboundMessage(array $row): ?InboundMessage
    {
        $from = (string) ($row['number'] ?? '');
        $body = (string) ($row['message'] ?? '');

        if ($from === '') {
            return null;
        }

        return new InboundMessage(
            from:       $from,
            to:         (string) ($row['simSlot'] ?? ''),
            body:       $body,
            providerId: isset($row['ID']) ? (string) $row['ID'] : null,
            optOutType: $this->detectItalianOptOut($body),
            meta:       array_filter([
                'device_id'  => $row['deviceID'] ?? null,
                'sim_slot'   => $row['simSlot'] ?? null,
                'user_id'    => $row['userID'] ?? null,
                'group_id'   => $row['groupID'] ?? null,
                'sent_date'  => $row['sentDate'] ?? null,
            ], static fn($v) => $v !== null && $v !== ''),
        );
    }

    /**
     * SendApp serves Italian users primarily — pre-detect "STOP TUTTO" so it
     * counts as an opt-out even though KeywordMatcher's CTIA-compliant
     * single-word match would skip it.
     */
    private function detectItalianOptOut(string $body): ?string
    {
        $normalized = strtolower(trim($body));
        if ($normalized === 'stop tutto' || $normalized === 'cancella') {
            return 'stop';
        }
        return null;
    }

    private function resolveOptOutManager(): ?\WSms\Messaging\Inbound\OptOutManager
    {
        try {
            $manager = Bootstrap::get('messaging.optout_manager');
        } catch (\Throwable $e) {
            return null;
        }
        return $manager instanceof \WSms\Messaging\Inbound\OptOutManager ? $manager : null;
    }

    private function callbackToken(): string
    {
        return hash_hmac('sha256', 'sendapp-callback', (string) $this->getSharedConfig('api_key'));
    }
}

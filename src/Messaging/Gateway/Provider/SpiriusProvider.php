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
 * Spirius (spirius.com) — Nordic SMS provider.
 *
 * Endpoints from developer.spirius.com (`rest.spirius.com/v1/...`). Auth is
 * either HMAC-SHA256 ("SpiriusSmsV1") or HTTP Basic; both schemes use the
 * same Username + Shared Key from the customer portal. Webhooks (DLR +
 * inbound MO) are NOT signed — this provider authenticates them via a
 * shared `callback_token` appended as `?token=…` to the registered URL.
 *
 * The HMAC canonical string follows the Python SDK at
 * https://raw.githubusercontent.com/spiriusab/rest-api-examples/main/examples/python3/spirius_http_client.py
 * — note the upstream PHP example uses literal '\n' (escaped) which is a
 * bug; the Python form uses real newlines and is the correct one.
 *
 * Out of scope (no public REST endpoint or no WSMS abstraction yet):
 * - Voice (Text2Voice has no REST endpoint documented).
 * - RCS (marketed as "early access", no public reference).
 * - ReplyPath two-way virtual-number correlation (no WSMS abstraction).
 * - Batch SMS API (1M-recipient bulk; WSMS already fans out via
 *   MessageDispatcher, no benefit until WSMS adds a batch-channel
 *   abstraction).
 * - Verify-as-a-Service (Spirius offers no provider-managed OTP endpoint;
 *   templated send exists only on the separate Batch SMS API and would
 *   surface through a future SupportsVerify interface).
 */
class SpiriusProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://rest.spirius.com/v1';

    public function getId(): string
    {
        return 'spirius';
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
                    'description' => __('Spirius portal username (issued via sales onboarding at portal.spirius.com).', 'wp-sms'),
                ],
                'shared_key' => [
                    'type'        => 'secret',
                    'label'       => __('Shared Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Shared key from the Spirius portal — used for HMAC-SHA256 signing or HTTP Basic auth.', 'wp-sms'),
                ],
                'auth_mode' => [
                    'type'        => 'select',
                    'label'       => __('Authentication', 'wp-sms'),
                    'required'    => true,
                    'default'     => 'hmac',
                    'description' => __('HMAC-SHA256 is the documented preferred scheme; HTTP Basic is accepted on the same endpoints if HMAC ever misbehaves.', 'wp-sms'),
                    'options'     => [
                        ['value' => 'hmac',  'label' => __('HMAC-SHA256 (recommended)', 'wp-sms')],
                        ['value' => 'basic', 'label' => __('HTTP Basic', 'wp-sms')],
                    ],
                ],
                'callback_token' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Random secret appended to your DLR/inbound URLs as ?token=… — Spirius does not sign webhooks, so without this token the callback endpoints reject all traffic.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Default Sender', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'WSMS',
                        'description' => __('E.164 number (+46…), national number, alphanumeric ID (≤11 characters), or short code.', 'wp-sms'),
                    ],
                    'from_type' => [
                        'type'     => 'select',
                        'label'    => __('Sender Type', 'wp-sms'),
                        'required' => true,
                        'default'  => 'alphanumeric',
                        'options'  => [
                            ['value' => 'international', 'label' => __('International (E.164)', 'wp-sms')],
                            ['value' => 'national',      'label' => __('National', 'wp-sms')],
                            ['value' => 'alphanumeric',  'label' => __('Alphanumeric', 'wp-sms')],
                            ['value' => 'short',         'label' => __('Short Code', 'wp-sms')],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username  = (string) $this->getSharedConfig('username', '');
        $sharedKey = (string) $this->getSharedConfig('shared_key', '');

        if ($username === '' || $sharedKey === '') {
            return DeliveryResult::failed(__('Spirius credentials not configured', 'wp-sms'));
        }

        $from     = (string) $this->getChannelConfig('sms', 'from', '');
        $fromType = (string) $this->getChannelConfig('sms', 'from_type', 'alphanumeric');

        if ($from === '') {
            return DeliveryResult::failed(__('Spirius default sender is not configured', 'wp-sms'));
        }

        $body = [
            'to'             => [$message->getRecipient()],
            'from'           => $from,
            'fromType'       => $fromType,
            'message'        => $message->getBody(),
            'deliveryReport' => true,
        ];

        $callbackToken = (string) $this->getSharedConfig('callback_token', '');
        if ($callbackToken !== '') {
            $body['dlrCallbackUrl'] = $this->callbackUrlWithToken($this->getStatusCallbackUrl(), $callbackToken);
        }

        $meta = $message->getMeta();
        if (!empty($meta['external_message_id'])) {
            $body['externalMessageId'] = (string) $meta['external_message_id'];
        }

        $jsonBody = wp_json_encode($body);
        $path     = '/sms/mt/send';
        $headers  = $this->buildAuthHeaders('POST', '/v1' . $path, $jsonBody, $username, $sharedKey) + [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        $result = $this->httpPost(self::API_BASE . $path, [
            'headers' => $headers,
            'body'    => $jsonBody,
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Spirius credentials', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300 && is_array($data)) {
            $providerId = isset($data['transactionId']) ? (string) $data['transactionId'] : null;
            return DeliveryResult::queued($providerId);
        }

        $error = is_array($data) ? ($data['detail'] ?? $data['message'] ?? null) : null;

        return DeliveryResult::failed(
            $error ?: sprintf('HTTP %d', $result['code']),
            meta: array_filter([
                'spirius_code' => $result['code'] ?: null,
            ]),
        );
    }

    /**
     * Spirius does not expose a public balance endpoint; `remainingRequestQuota`
     * in send responses is a per-window rate-limit token, not an account balance.
     */
    public function getCredit(): ?string
    {
        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        $username  = (string) $this->getSharedConfig('username', '');
        $sharedKey = (string) $this->getSharedConfig('shared_key', '');

        if ($username === '' || $sharedKey === '') {
            return TestConnectionResult::error(__('Username and Shared Key are required', 'wp-sms'));
        }

        $path    = '/sms/mo/';
        $headers = $this->buildAuthHeaders('GET', '/v1' . $path, '', $username, $sharedKey);

        $result = $this->httpGet(self::API_BASE . $path, [
            'headers' => $headers,
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Spirius Username or Shared Key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Spirius');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        return TestConnectionResult::ok(__('Connected to Spirius', 'wp-sms'));
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
        $payload = $this->callbackPayload($request);

        $providerId = $payload['transactionId'] ?? $request->get_param('transactionId');
        if (empty($providerId)) {
            return [];
        }

        $resultCode = $payload['result'] ?? $request->get_param('result');
        if ($resultCode === null || $resultCode === '') {
            return [];
        }
        $resultCode = (int) $resultCode;

        $statusCode = $payload['statusCode'] ?? $request->get_param('statusCode');
        $statusCode = $statusCode === null || $statusCode === '' ? null : (int) $statusCode;

        $detail = (string) ($payload['detail'] ?? $request->get_param('detail') ?? '');

        // Spirius result enum: 1 = Delivered, 2 = Failed, 32 = Pending.
        // statusCode (only meaningful when result=2): 1 undeliverable, 2 unroutable,
        // 3 validity expired, 4 unknown txn, 5 internal.
        [$normalized, $permanent] = match ($resultCode) {
            1       => ['delivered', false],
            32      => ['queued',    false],
            2       => ['failed',    in_array($statusCode, [1, 2, 3, 4], true)],
            default => [(string) $resultCode, false],
        };

        return [new StatusUpdate(
            providerId:   (string) $providerId,
            status:       $normalized,
            errorCode:    $normalized === 'failed' && $statusCode !== null ? (string) $statusCode : null,
            errorMessage: $normalized === 'failed' ? ($detail !== '' ? sprintf('Spirius: %s', $detail) : sprintf('Spirius statusCode %d', (int) $statusCode)) : null,
            permanent:    $permanent,
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
        $payload = $this->callbackPayload($request);

        $from = (string) ($payload['from'] ?? $request->get_param('from') ?? '');
        if ($from === '') {
            return [];
        }

        $resultCode = $payload['result'] ?? $request->get_param('result');
        if ($resultCode !== null && $resultCode !== '' && (int) $resultCode !== 1) {
            // MO `result`: 1 ok, 2 unknown txn, 3 no more, 4 internal — only 1
            // represents an actual inbound message.
            return [];
        }

        $to            = (string) ($payload['to'] ?? $request->get_param('to') ?? '');
        $body          = (string) ($payload['message'] ?? $request->get_param('message') ?? '');
        $transactionId = $payload['transactionId'] ?? $request->get_param('transactionId');
        $providerId    = $transactionId !== null && $transactionId !== '' ? (string) $transactionId : null;

        $meta = array_filter([
            'timestamp' => $payload['timestamp'] ?? $request->get_param('timestamp'),
            'fromType'  => $payload['fromType'] ?? $request->get_param('fromType'),
            'toType'    => $payload['toType'] ?? $request->get_param('toType'),
            'type'      => $payload['type'] ?? $request->get_param('type'),
        ], fn($v) => $v !== null && $v !== '');

        return [new InboundMessage(
            from:       $from,
            to:         $to,
            body:       $body,
            providerId: $providerId,
            meta:       $meta,
        )];
    }

    // --- Internal ---

    /**
     * @return array<string,string>
     */
    private function buildAuthHeaders(string $verb, string $path, string $jsonBody, string $username, string $sharedKey): array
    {
        $authMode = (string) $this->getSharedConfig('auth_mode', 'hmac');

        if ($authMode === 'basic') {
            return [
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $sharedKey),
            ];
        }

        $timestamp  = (string) time();
        $bodyDigest = sha1($jsonBody);
        $message    = implode("\n", ['SpiriusSmsV1', $timestamp, strtoupper($verb), $path, $bodyDigest]);
        $signature  = base64_encode(hash_hmac('sha256', $message, $sharedKey, true));

        return [
            'X-SMS-Timestamp' => $timestamp,
            'Authorization'   => 'SpiriusSmsV1 ' . $username . ':' . $signature,
        ];
    }

    /**
     * Append a `token=…` query param to a URL without disturbing existing args.
     */
    private function callbackUrlWithToken(string $url, string $token): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 'token=' . rawurlencode($token);
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

    /**
     * Spirius webhooks document JSON bodies, but keep query/form params as a
     * fallback so test fixtures and edge-case configurations both work.
     *
     * @return array<string,mixed>
     */
    private function callbackPayload(\WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        return is_array($json) ? $json : [];
    }
}

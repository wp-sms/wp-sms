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
 * SMSto — global SMS + Viber aggregator.
 *
 * Bearer-token auth covers both channels. Send endpoints:
 *   SMS         POST https://api.sms.to/sms/send
 *   Flash SMS   POST https://api.sms.to/fsms/send       (when $meta['flash'] is truthy)
 *   Viber       POST https://api.sms.to/viber/send      (supports image/target/caption)
 *
 * Balance: POST https://auth.sms.to/api/balance (the same Bearer key, returns {balance}).
 *
 * Webhook signature (status + inbound): header `X-SMSTo-Signature: t=<unix>,s=<hex>`,
 * algorithm hash_hmac('sha256', body . '.' . timestamp, secret). The shared secret
 * is configured per-endpoint in the SMSto dashboard and pasted into the Webhook Secret
 * field. If no secret is set, signed callbacks are rejected (open callbacks would be
 * an unauthenticated write path, so we fail closed).
 */
class SmstoProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsOptOutDetection
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.sms.to';
    private const AUTH_BASE = 'https://auth.sms.to';

    public function getId(): string
    {
        return 'smsto';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'viber'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generate at https://sms.to/app under API → API Keys.', 'wp-sms'),
                ],
                'callback_secret' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Secret', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Set in the SMSto dashboard under Webhook Management. Required to receive delivery and inbound callbacks securely. Leave blank to disable callbacks.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Default Sender ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Alphanumeric Sender ID or phone number registered in your SMSto account. Optional — leave blank to use the account default.', 'wp-sms'),
                        'placeholder' => 'MyCompany',
                    ],
                ],
                'viber' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Viber Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('The approved Viber Business sender registered in your SMSto account. Required — Viber rejects messages without a registered sender.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('SMSto API Key not configured', 'wp-sms'));
        }

        $channel = $message->getChannel();
        return match ($channel) {
            'sms'   => $this->sendSms($message, $apiKey),
            'viber' => $this->sendViber($message, $apiKey),
            default => DeliveryResult::failed(sprintf(__('SMSto does not support channel %s', 'wp-sms'), $channel)),
        };
    }

    private function sendSms(MessageInterface $message, string $apiKey): DeliveryResult
    {
        $meta = $message->getMeta();
        $body = [
            'to'           => $message->getRecipient(),
            'message'      => $message->getBody(),
            'callback_url' => $this->getStatusCallbackUrl(),
        ];

        $sender = $this->getChannelConfig('sms', 'sender_id');
        if ($sender) {
            $body['sender_id'] = $sender;
        }

        if (!empty($meta['scheduled_for'])) {
            $body['scheduled_for'] = (string) $meta['scheduled_for'];
            if (!empty($meta['timezone'])) {
                $body['timezone'] = (string) $meta['timezone'];
            }
        }

        // Flash SMS routes to a different endpoint per the SDK's setType('fsms').
        $endpoint = !empty($meta['flash']) ? '/fsms/send' : '/sms/send';

        return $this->postSend(self::API_BASE . $endpoint, $body, $apiKey);
    }

    private function sendViber(MessageInterface $message, string $apiKey): DeliveryResult
    {
        $meta = $message->getMeta();
        $body = [
            'to'           => $message->getRecipient(),
            'message'      => $message->getBody(),
            'callback_url' => $this->getStatusCallbackUrl(),
        ];

        $sender = $this->getChannelConfig('viber', 'sender_id');
        if ($sender) {
            $body['sender_id'] = $sender;
        }

        // Viber-specific optional fields per the SDK's ViberMessage class.
        $mediaUrls = $meta['media_urls'] ?? [];
        if (!empty($mediaUrls[0])) {
            $body['viber_image_url'] = $mediaUrls[0];
        } elseif (!empty($meta['viber_image_url'])) {
            $body['viber_image_url'] = (string) $meta['viber_image_url'];
        }
        if (!empty($meta['viber_target_url'])) {
            $body['viber_target_url'] = (string) $meta['viber_target_url'];
        }
        if (!empty($meta['viber_caption'])) {
            $body['viber_caption'] = (string) $meta['viber_caption'];
        }

        if (!empty($meta['scheduled_for'])) {
            $body['scheduled_for'] = (string) $meta['scheduled_for'];
            if (!empty($meta['timezone'])) {
                $body['timezone'] = (string) $meta['timezone'];
            }
        }

        return $this->postSend(self::API_BASE . '/viber/send', $body, $apiKey);
    }

    private function postSend(string $url, array $body, string $apiKey): DeliveryResult
    {
        $result = $this->httpPost($url, [
            'headers' => $this->authHeaders($apiKey, 'application/json'),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true) ?: [];

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid SMSto API Key', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300 && !empty($data['success'])) {
            $providerId = $data['message_id']
                ?? ($data['message']['message_id'] ?? null)
                ?? ($data['data']['message_id'] ?? null)
                ?? null;
            return DeliveryResult::queued($providerId ? (string) $providerId : null);
        }

        $errorMsg = $data['message'] ?? $data['error'] ?? sprintf('HTTP %d', $result['code']);
        if (!empty($data['errors']) && is_array($data['errors'])) {
            $first = reset($data['errors']);
            if (is_array($first)) {
                $first = reset($first);
            }
            if (is_string($first) && $first !== '') {
                $errorMsg = $first;
            }
        }

        return DeliveryResult::failed(
            $errorMsg,
            meta: array_filter([
                'smsto_status' => $result['code'] ?: null,
                'smsto_code'   => isset($data['code']) ? (string) $data['code'] : null,
            ]),
        );
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpGet(self::AUTH_BASE . '/api/balance', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        $balance = $data['balance'] ?? ($data['data']['balance'] ?? null);
        if ($balance === null) {
            return null;
        }

        return number_format((float) $balance, 4) . ' EUR';
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::AUTH_BASE . '/api/balance', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid SMSto API Key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'SMSto');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = $data['balance'] ?? ($data['data']['balance'] ?? 'N/A');

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s', 'wp-sms'), $balance),
            ['balance' => $balance],
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifySmstoSignature($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $params = $this->callbackParams($request);

        $providerId = $params['messageId'] ?? $params['message_id'] ?? $params['trackingId'] ?? null;
        $rawStatus = $params['status'] ?? null;

        if (!$providerId || !$rawStatus) {
            return [];
        }

        $upper = strtoupper((string) $rawStatus);
        $normalized = match ($upper) {
            'QUEUED', 'SCHEDULED'                => 'queued',
            'SENT', 'ACCEPTED'                   => 'sent',
            'DELIVERED', 'READ'                  => 'delivered',
            'FAILED', 'UNDELIVERED', 'REJECTED', 'EXPIRED', 'OPTOUT', 'OPTED_OUT' => 'failed',
            default                              => strtolower((string) $rawStatus),
        };

        $errorCode = isset($params['errorCode']) ? (string) $params['errorCode']
            : (isset($params['error_code']) ? (string) $params['error_code'] : null);

        return [new StatusUpdate(
            providerId:   (string) $providerId,
            status:       $normalized,
            errorCode:    $errorCode,
            errorMessage: $normalized === 'failed' ? sprintf('SMSto: %s', $upper) : null,
            permanent:    $this->isPermanentSmstoStatus($upper),
            unsubscribe:  in_array($upper, ['OPTOUT', 'OPTED_OUT'], true),
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifySmstoSignature($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $params = $this->callbackParams($request);

        $from = (string) ($params['from'] ?? '');
        if ($from === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($params['to'] ?? ''),
            body:       (string) ($params['message'] ?? ''),
            providerId: $params['messageId'] ?? $params['message_id'] ?? null,
            meta:       array_filter([
                'parts'        => $params['parts'] ?? null,
                'received_at'  => $params['receivedDate'] ?? $params['received_at'] ?? null,
            ]),
        )];
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        $message = strtolower((string) ($result->error ?? ''));
        if ($message === '') {
            return false;
        }
        // SMSto returns the recipient's opt-out as a 422 with a message body that
        // mentions "opted out" / "opt-out" / "unsubscribed". The dedicated opt-out
        // webhook lands on /callbacks/smsto/status as status=OPTOUT (handled in
        // parseStatusCallback via the unsubscribe flag), so this method only needs
        // to catch synchronous send-time rejections.
        return str_contains($message, 'opt-out')
            || str_contains($message, 'opted out')
            || str_contains($message, 'unsubscrib');
    }

    // --- Internal ---

    private function authHeaders(string $apiKey, ?string $contentType = null): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept'        => 'application/json',
        ];
        if ($contentType) {
            $headers['Content-Type'] = $contentType;
        }
        return $headers;
    }

    /**
     * SMSto sends callbacks as form-encoded POST by default but the dashboard can
     * be flipped to JSON. Try JSON first, fall back to form params.
     */
    private function callbackParams(\WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        if (is_array($json) && !empty($json)) {
            return $json;
        }
        return $request->get_params();
    }

    /**
     * Verify the X-SMSTo-Signature header. Format: `t=<unix>,s=<hex>`.
     *
     *   expected = hash_hmac('sha256', rawBody . '.' . timestamp, callback_secret)
     *
     * Reference: github.com/intergo/sms.to-php Module/Webhook/Signature.php.
     */
    private function verifySmstoSignature(\WP_REST_Request $request): bool
    {
        $secret = $this->getSharedConfig('callback_secret');
        if (!$secret) {
            // Fail closed — accepting unsigned callbacks would let anyone forge
            // delivery/inbound events for this account.
            return false;
        }

        $header = $request->get_header('x-smsto-signature');
        if (empty($header)) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || !str_contains($segment, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $segment, 2);
            $parts[trim($k)] = trim($v);
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['s'] ?? '';
        if ($timestamp === '' || $signature === '') {
            return false;
        }

        $body = $request->get_body() ?? '';
        $expected = hash_hmac('sha256', $body . '.' . $timestamp, $secret);

        return hash_equals($expected, $signature);
    }

    private function isPermanentSmstoStatus(string $status): bool
    {
        return in_array($status, [
            'REJECTED',
            'EXPIRED',
            'UNDELIVERED',
            'OPTOUT',
            'OPTED_OUT',
        ], true);
    }
}

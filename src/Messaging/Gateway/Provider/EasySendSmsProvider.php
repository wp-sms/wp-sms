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

/**
 * EasySendSMS — global SMS aggregator (200+ countries, 1,100+ networks).
 *
 * Auth: apikey header (REST API). The legacy username/password HTTP API is
 * intentionally not used — the REST surface returns structured JSON, has a
 * documented error envelope, and a single-secret credential.
 *
 * Send:    POST https://restapi.easysendsms.app/v1/rest/sms/send
 * Balance: GET  https://restapi.easysendsms.app/v1/rest/sms/balance
 *
 * DLR webhook is configured per-account in the EasySendSMS dashboard (not
 * per-message via an ackurl body field). EasySendSMS does not document a
 * signing scheme, so the callback URL embeds an HMAC token tied to the API
 * key and we reject any request whose token doesn't match.
 *
 * Inbound MO is not implemented — the field names for incoming messages are
 * not in published EasySendSMS docs.
 */
class EasySendSmsProvider extends AbstractProvider implements SupportsStatusCallback
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://restapi.easysendsms.app/v1/rest/sms';

    public function getId(): string
    {
        return 'easysendsms';
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
                    'description' => __('Generated under Account Settings → API in your EasySendSMS dashboard.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Numeric (max 15 digits) or alphanumeric (max 11 chars including at least one letter).', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        $from = $this->getChannelConfig('sms', 'from');

        if (!$apiKey || !$from) {
            return DeliveryResult::failed(__('EasySendSMS credentials not configured', 'wp-sms'));
        }

        $body = [
            'from' => $from,
            'to'   => ltrim($message->getRecipient(), '+'),
            'text' => $message->getBody(),
            'type' => preg_match('/[^\x00-\x7F]/', $message->getBody()) ? 1 : 0,
        ];

        $result = $this->httpPost(self::API_BASE . '/send', [
            'headers' => $this->authHeaders($apiKey),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid EasySendSMS API key', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] >= 200 && $result['code'] < 300 && (($data['status'] ?? null) === 'OK')) {
            $messageId = $this->extractMessageId($data['messageIds'][0] ?? '');
            return DeliveryResult::sent(providerId: $messageId);
        }

        $errorCode = $data['error'] ?? null;
        $description = $data['description'] ?? null;

        return DeliveryResult::failed(
            $description ?? sprintf(__('EasySendSMS send failed (code %s)', 'wp-sms'), $errorCode ?? $result['code']),
            meta: array_filter(['easysendsms_code' => $errorCode]),
        );
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/balance', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        return isset($data['balance']) ? (string) $data['balance'] : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/balance', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid API Key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'EasySendSMS');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = $data['balance'] ?? 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s credits', 'wp-sms'), $balance),
            ['balance' => $balance],
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
        if (!$this->getSharedConfig('api_key')) {
            return false;
        }
        return hash_equals($this->callbackToken(), (string) ($request->get_param('token') ?? ''));
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $smsId = $request->get_param('sms_id');
        if (empty($smsId)) {
            return [];
        }

        $response = (string) ($request->get_param('response') ?? '');
        $normalized = match ($response) {
            'DELIVRD'            => 'delivered',
            'EXPIRED', 'UNDELIV' => 'failed',
            default              => 'sent',
        };

        return [new StatusUpdate(
            providerId:   (string) $smsId,
            status:       $normalized,
            errorCode:    $response !== '' ? $response : null,
            errorMessage: $normalized === 'failed'
                ? sprintf('EasySendSMS DLR: %s', $response ?: 'unknown')
                : null,
            permanent:    in_array($response, ['EXPIRED', 'UNDELIV'], true),
        )];
    }

    // --- Internal ---

    private function authHeaders(string $apiKey): array
    {
        return [
            'apikey'       => $apiKey,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }

    private function callbackToken(): string
    {
        return hash_hmac('sha256', 'easysendsms-callback', (string) $this->getSharedConfig('api_key'));
    }

    private function extractMessageId(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }
        // Response shape: "OK: <uuid>". Strip the prefix; otherwise return raw.
        if (str_starts_with($raw, 'OK: ')) {
            return substr($raw, 4);
        }
        return $raw;
    }
}

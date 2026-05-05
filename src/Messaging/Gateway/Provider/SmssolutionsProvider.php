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
 * SMS Solutions Australia — Australian SMS gateway built on the eziapi.com
 * platform. A single API Key (header `key`) authenticates every request to
 * https://eziapi.com.
 *
 * The send-side `callback` parameter on POST /v3/sms is a per-message DLR
 * webhook URL — eziapi GETs it back with id, status, timestamp once delivery
 * state changes. Inbound replies hit the account-level `mo_callback_url`
 * configured in the SMSsolutions Settings page (also a GET).
 *
 * Both callback URLs WSMS publishes carry a `?token=` query argument derived
 * from the API key (HMAC-SHA256), and validate*Callback() compares with
 * hash_equals — same shape as AfricasTalkingProvider.
 */
class SmssolutionsProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsOptOutDetection
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://eziapi.com';

    public function getId(): string
    {
        return 'smssolutions';
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
                    'description' => __('Find your single API Key on the Settings page of the SMSsolutions web platform after login.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender (mask)', 'wp-sms'),
                        'required'    => false,
                        'placeholder' => 'MyCompany',
                        'description' => __('Alphanumeric Sender ID up to 11 characters (subject to telco approval). Leave blank to use the account default.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if ($message->getChannel() !== 'sms') {
            return DeliveryResult::failed(sprintf(__('SMSsolutions does not support channel %s', 'wp-sms'), $message->getChannel()));
        }

        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('SMSsolutions API key not configured', 'wp-sms'));
        }

        $body = [
            'recipient' => ltrim((string) $message->getRecipient(), '+'),
            'content'   => $message->getBody(),
            'callback'  => $this->getStatusCallbackUrl(),
        ];

        $from = $this->getChannelConfig('sms', 'from');
        if ($from) {
            $body['mask'] = $from;
        }

        $result = $this->httpPost(self::API_BASE . '/v3/sms', [
            'headers' => $this->authHeaders($apiKey, 'application/x-www-form-urlencoded'),
            'body'    => $body,
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        $data = is_array($data) ? $data : [];

        $code = $result['code'];
        $error = isset($data['error']) ? (string) $data['error'] : '';

        if ($code === 401 || $code === 403 || ($code === 400 && $error === 'Invalid API key')) {
            return DeliveryResult::failed(__('Invalid SMSsolutions credentials', 'wp-sms'));
        }

        if ($code >= 200 && $code < 300 && !empty($data['id'])) {
            return DeliveryResult::queued((string) $data['id']);
        }

        return DeliveryResult::failed(
            $error !== '' ? $error : sprintf('HTTP %d', $code),
            meta: array_filter(['eziapi_var' => isset($data['var']) ? (string) $data['var'] : null]),
        );
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/v3/settings', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }
        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['balance'])) {
            return null;
        }

        return (string) $data['balance'];
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/v3/settings', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid SMSsolutions credentials', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'SMSsolutions');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = $data['balance'] ?? 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s credits', 'wp-sms'), $balance),
            ['balance' => (string) $balance],
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
        $id = $request->get_param('id');
        $rawStatus = $request->get_param('status');

        if (empty($id) || empty($rawStatus)) {
            return [];
        }

        $rawStatus = (string) $rawStatus;
        $lower = strtolower($rawStatus);

        $normalized = match ($lower) {
            'queued'              => 'queued',
            'sent'                => 'sent',
            'delivered'           => 'delivered',
            'bounced',
            'failed',
            'insufficient_credit' => 'failed',
            default               => $lower,
        };

        $permanent = in_array($lower, ['bounced', 'failed'], true);

        return [new StatusUpdate(
            providerId:   (string) $id,
            status:       $normalized,
            errorCode:    null,
            errorMessage: $normalized === 'failed' ? sprintf('SMSsolutions: %s', $rawStatus) : null,
            permanent:    $permanent,
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url(
            'callbacks/' . $this->getId() . '/inbound',
            ['token' => $this->callbackToken()],
        );
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        if (!$this->getSharedConfig('api_key')) {
            return false;
        }
        return hash_equals($this->callbackToken(), (string) ($request->get_param('token') ?? ''));
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $from = (string) ($request->get_param('from') ?? '');
        if ($from === '') {
            return [];
        }

        $id = $request->get_param('id');

        return [new InboundMessage(
            from:       $from,
            to:         '',
            body:       (string) ($request->get_param('message') ?? ''),
            providerId: $id !== null ? (string) $id : null,
            meta:       array_filter([
                'timestamp' => $request->get_param('timestamp'),
                'reply_to'  => $request->get_param('reply_to'),
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
        return str_contains($message, 'opt-out')
            || str_contains($message, 'opted out')
            || str_contains($message, 'unsubscrib');
    }

    // --- Internal ---

    private function authHeaders(string $apiKey, ?string $contentType = null): array
    {
        $headers = [
            'key'    => $apiKey,
            'Accept' => 'application/json',
        ];
        if ($contentType) {
            $headers['Content-Type'] = $contentType;
        }
        return $headers;
    }

    private function callbackToken(): string
    {
        return hash_hmac('sha256', 'smssolutions-callback', (string) $this->getSharedConfig('api_key'));
    }
}

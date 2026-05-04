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
 * Global Voice — A2P SMS aggregator on the Alaris Labs platform.
 *
 * REST: POST /rest/send_sms with Bearer token; balance via GET /rest/account.
 * Webhooks (DLR + MO) are unsigned by Alaris — security relies on a shared
 * secret operators paste into the portal callback URL as ?token=…, which
 * the provider checks on every callback (reject by default if unset).
 */
class GlobalVoiceProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://rest.global-voice.net/rest';

    public function getId(): string
    {
        return 'globalvoice';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'token' => [
                    'type'        => 'secret',
                    'label'       => __('API Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Bearer token from your Global Voice retail dashboard → API connections → Tokens.', 'wp-sms'),
                    'placeholder' => 'eyJhbGci...',
                ],
                'acc_id' => [
                    'type'        => 'string',
                    'label'       => __('Account ID', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Optional. Alaris account ID. Leave blank if your token is account-scoped; fill in if Global Voice support tells you it is required.', 'wp-sms'),
                    'placeholder' => 'e.g., 12345',
                ],
                'webhook_token' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Shared secret you paste into the callback URLs in the Global Voice portal as ?token=…. Required if you want delivery receipts or inbound SMS — without it, callbacks are rejected.', 'wp-sms'),
                    'placeholder' => __('Generate a random string', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Alphanumeric (max 11 chars) or numeric sender shown to recipients. Subject to per-country regulatory rules.', 'wp-sms'),
                        'placeholder' => 'MyBrand',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $token  = $this->getSharedConfig('token');
        $accId  = $this->getSharedConfig('acc_id');
        $sender = $this->getChannelConfig('sms', 'sender_id');

        if (!$token || !$sender) {
            return DeliveryResult::failed(__('Global Voice credentials not configured', 'wp-sms'));
        }

        $body = [
            'from'    => $sender,
            'to'      => $message->getRecipient(),
            'message' => $message->getBody(),
        ];
        if (!empty($accId)) {
            $body['acc_id'] = $accId;
        }

        $result = $this->httpPost(self::API_BASE . '/send_sms', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
            'body' => $body,
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            $data = [];
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Global Voice token', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300) {
            $messageId = $data['message_id'] ?? null;
            return DeliveryResult::sent(is_string($messageId) || is_numeric($messageId) ? (string) $messageId : null);
        }

        $error = $data['status']
            ?? $data['error_message']
            ?? $data['message']
            ?? $data['error']
            ?? sprintf('HTTP %d', $result['code']);

        return DeliveryResult::failed(sprintf('Global Voice: %s', $error));
    }

    public function getCredit(): ?string
    {
        $token = $this->getSharedConfig('token');
        if (!$token) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/account', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        $balance  = $data[0]['balance']       ?? null;
        $currency = $data[0]['currency_code'] ?? null;

        if ($balance === null) {
            return null;
        }

        return trim($balance . ($currency ? ' ' . $currency : ''));
    }

    public function testConnection(): TestConnectionResult
    {
        $token = $this->getSharedConfig('token');
        if (!$token) {
            return TestConnectionResult::error(__('Enter your Global Voice API token first.', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/account', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Global Voice token', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Global Voice');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance  = $data[0]['balance']       ?? null;
        $currency = $data[0]['currency_code'] ?? '';

        if ($balance === null) {
            return TestConnectionResult::ok(__('Connected to Global Voice', 'wp-sms'));
        }

        $display = trim($balance . ($currency ? ' ' . $currency : ''));
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $display),
            ['balance' => $balance, 'currency' => $currency],
        );
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
        $params = $this->collectParams($request);

        $messageId = (string) ($params['message_id'] ?? '');
        if ($messageId === '') {
            return [];
        }

        $deliveryStatus = strtoupper((string) ($params['delivery_status'] ?? ''));
        $resultCode     = strtolower((string) ($params['result_code'] ?? ''));

        $isDelivered      = $deliveryStatus === 'DELIVRD' || $resultCode === '0x0000000';
        $isPermanentFail  = $resultCode === '0x0000001'; // UNDELIV

        if ($isDelivered) {
            return [new StatusUpdate(
                providerId: $messageId,
                status:     'delivered',
                permanent:  false,
            )];
        }

        $detail = $deliveryStatus !== ''
            ? $deliveryStatus
            : ($resultCode !== '' ? $resultCode : 'unknown');

        return [new StatusUpdate(
            providerId:   $messageId,
            status:       'failed',
            errorCode:    $resultCode !== '' ? $resultCode : null,
            errorMessage: sprintf('Global Voice: %s', $detail),
            permanent:    $isPermanentFail,
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
        $params = $this->collectParams($request);

        $from = (string) ($params['ani'] ?? '');
        if ($from === '') {
            return [];
        }

        $messageId = $params['message_id'] ?? null;

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($params['dnis'] ?? ''),
            body:       (string) ($params['message'] ?? ''),
            providerId: $messageId !== null && $messageId !== '' ? (string) $messageId : null,
        )];
    }

    // --- Internal ---

    /**
     * Reject-by-default token check. Alaris does not sign callbacks — operators
     * configure a shared secret in WSMS and paste it into the portal callback
     * URL as ?token=…. Empty config or mismatched/missing param → reject.
     */
    private function verifyToken(\WP_REST_Request $request): bool
    {
        $configured = (string) ($this->getSharedConfig('webhook_token') ?? '');
        if ($configured === '') {
            return false;
        }

        $supplied = (string) ($request->get_param('token') ?? '');
        return $supplied !== '' && hash_equals($configured, $supplied);
    }

    /**
     * Read callback params from query string first, falling back to JSON or
     * form body. Alaris portals can be configured to push either way.
     *
     * @return array<string, mixed>
     */
    private function collectParams(\WP_REST_Request $request): array
    {
        $params = $request->get_params();

        $json = $request->get_json_params();
        if (is_array($json)) {
            $params = array_merge($json, $params);
        }

        return $params;
    }
}

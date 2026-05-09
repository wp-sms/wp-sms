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
 * Apifon — Greece-based aggregator with global SMS delivery via HMAC-signed REST.
 *
 * Webhooks: Apifon does not sign outbound callbacks (relies on IP allowlist).
 * Per plugin policy "no signing → reject-by-default with a configurable token",
 * the operator sets a webhook_token here and embeds it as a query parameter on
 * the callback URL configured in Mookee. Callbacks without a matching token are rejected.
 *
 * TODO(viber): Apifon /im/send supports Viber Business Messages, but 'viber'
 * is not currently a recognized WSMS channel slug — defer until the enum lands.
 *
 * TODO(verify): Apifon /otp/request + /otp/verify is Verify-as-a-Service —
 * defer wiring until a SupportsVerify interface exists in the messaging layer.
 */
class ApifonProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://ars.apifon.com';
    private const SEND_PATH = '/services/api/v1/sms/send';
    private const BALANCE_PATH = '/services/api/v1/balance';

    public function getId(): string
    {
        return 'apifon';
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
                    'type'        => 'string',
                    'label'       => __('API Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('HMAC token from Mookee (Account > Developers > Add Token).', 'wp-sms'),
                ],
                'secret' => [
                    'type'        => 'secret',
                    'label'       => __('API Secret Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Secret key shown alongside the API token in Mookee. Used to HMAC-sign requests.', 'wp-sms'),
                ],
                'webhook_token' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Random string you generate. Append it as ?token=… to the callback URLs configured in Mookee. Apifon does not sign outbound webhooks, so this token is what authenticates them.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Default sender ID configured for the API token in Mookee. Up to 11 alphanumeric characters or a numeric short code.', 'wp-sms'),
                        'placeholder' => 'MyBrand',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $token = $this->getSharedConfig('token');
        $secret = $this->getSharedConfig('secret');

        if (!$token || !$secret) {
            return DeliveryResult::failed(__('Apifon credentials not configured', 'wp-sms'));
        }

        $senderId = $this->getChannelConfig('sms', 'sender_id');
        if (!$senderId) {
            return DeliveryResult::failed(__('Apifon Sender ID not configured', 'wp-sms'));
        }

        $payload = [
            'message' => array_filter([
                'text'         => $message->getBody(),
                'sender_id'    => $senderId,
                'callback_url' => $this->getStatusCallbackUrl() ?: null,
            ]),
            'subscribers' => [
                ['number' => $this->normalizeRecipient($message->getRecipient())],
            ],
        ];

        $body = wp_json_encode($payload);

        $result = $this->httpPost(self::API_BASE . self::SEND_PATH, [
            'headers' => $this->signRequest('POST', self::SEND_PATH, $body, $token, $secret),
            'body'    => $body,
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true) ?: [];

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Apifon credentials', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300) {
            $requestId = $data['request_id'] ?? null;
            $results = $data['results'] ?? [];
            $first = is_array($results) ? (reset($results) ?: []) : [];
            $providerId = $first['message_id'] ?? $requestId;

            return DeliveryResult::queued(
                $providerId ? (string) $providerId : null,
                meta: array_filter([
                    'request_id' => $requestId,
                ]),
            );
        }

        return DeliveryResult::failed(
            $data['status_description'] ?? $data['message'] ?? "HTTP {$result['code']}",
            meta: array_filter([
                'apifon_status_code' => isset($data['status_code']) ? (string) $data['status_code'] : null,
                'apifon_http_code'   => $result['code'] ?: null,
            ]),
        );
    }

    public function getCredit(): ?string
    {
        $token = $this->getSharedConfig('token');
        $secret = $this->getSharedConfig('secret');

        if (!$token || !$secret) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . self::BALANCE_PATH, [
            'headers' => $this->signRequest('GET', self::BALANCE_PATH, '', $token, $secret),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        $balance = $data['balance'] ?? null;
        if ($balance === null) {
            return null;
        }

        $currency = $data['currency'] ?? 'EUR';
        return number_format((float) $balance, 4) . ' ' . $currency;
    }

    public function testConnection(): TestConnectionResult
    {
        $token = $this->getSharedConfig('token');
        $secret = $this->getSharedConfig('secret');

        if (!$token || !$secret) {
            return TestConnectionResult::error(__('API Token and Secret Key are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . self::BALANCE_PATH, [
            'headers' => $this->signRequest('GET', self::BALANCE_PATH, '', $token, $secret),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid API Token or Secret Key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Apifon');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = $data['balance'] ?? 'N/A';
        $currency = $data['currency'] ?? 'EUR';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s %s', 'wp-sms'), $balance, $currency),
            ['balance' => $balance, 'currency' => $currency],
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        $token = $this->getSharedConfig('webhook_token');
        $args = $token ? ['token' => $token] : [];
        return RestRoute::url('callbacks/' . $this->getId() . '/status', $args);
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyWebhookToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $params = $request->get_json_params() ?: $request->get_params();

        $results = $params['results'] ?? [];
        if (!is_array($results) || empty($results)) {
            return [];
        }

        $updates = [];
        foreach ($results as $row) {
            if (!is_array($row)) {
                continue;
            }
            $messageId = $row['message_id'] ?? null;
            $rawStatus = (string) ($row['status'] ?? $row['status_description'] ?? '');
            if (!$messageId || $rawStatus === '') {
                continue;
            }

            $normalized = $this->normalizeStatus($rawStatus);
            $statusCode = isset($row['status_code']) ? (string) $row['status_code'] : null;

            $updates[] = new StatusUpdate(
                providerId:   (string) $messageId,
                status:       $normalized,
                errorCode:    $statusCode,
                errorMessage: $normalized === 'failed' ? sprintf('Apifon: %s', $rawStatus) : null,
                permanent:    $normalized === 'failed',
            );
        }

        return $updates;
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        $token = $this->getSharedConfig('webhook_token');
        $args = $token ? ['token' => $token] : [];
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound', $args);
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyWebhookToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $params = $request->get_json_params() ?: $request->get_params();

        $from = (string) ($params['destination'] ?? '');
        if ($from === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($params['sender_id'] ?? ''),
            body:       (string) ($params['reply_message'] ?? ''),
            providerId: isset($params['message_id']) ? (string) $params['message_id'] : null,
            meta:       array_filter([
                'date_received' => $params['date_received'] ?? null,
            ]),
        )];
    }

    // --- Internal ---

    /**
     * Compute the Apifon HMAC-SHA256 signature and return the headers required
     * to authenticate a request against the ARS API.
     *
     * Canonical string-to-sign:
     *   METHOD + "\n" + PATH + "\n" + BODY + "\n" + DATE
     * where DATE is RFC 2616 (GMT) e.g. "Thu, 09 May 2026 12:34:56 +0000".
     *
     * @return array<string, string>
     */
    private function signRequest(string $method, string $path, string $body, string $token, string $secret, ?string $date = null): array
    {
        $date = $date ?? gmdate('D, d M Y H:i:s') . ' +0000';
        $stringToSign = strtoupper($method) . "\n" . $path . "\n" . $body . "\n" . $date;
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $secret, true));

        return [
            'Authorization'    => "ApifonWS {$token}:{$signature}",
            'X-ApifonWS-Date'  => $date,
            'Content-Type'     => 'application/json',
            'Accept'           => 'application/json',
        ];
    }

    private function verifyWebhookToken(\WP_REST_Request $request): bool
    {
        $expected = (string) ($this->getSharedConfig('webhook_token') ?? '');
        if ($expected === '') {
            return false;
        }

        $provided = (string) ($request->get_param('token') ?? '');
        return $provided !== '' && hash_equals($expected, $provided);
    }

    private function normalizeStatus(string $raw): string
    {
        $upper = strtoupper($raw);
        return match ($upper) {
            'QUEUED', 'PENDING'           => 'queued',
            'SENT'                        => 'sent',
            'DELIVERED'                   => 'delivered',
            'FAILED', 'UNDELIVERABLE',
            'REJECTED', 'EXPIRED'         => 'failed',
            default                       => strtolower($raw),
        };
    }

    private function normalizeRecipient(string $recipient): string
    {
        return ltrim($recipient, '+');
    }
}

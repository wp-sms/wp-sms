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
 * TextMagic — global business SMS provider (REST v2).
 *
 * Auth uses two custom headers: X-TM-Username + X-TM-Key (the API v2 key).
 * Webhooks (delivery + inbound) share a single signing scheme: hex HMAC-SHA256
 * of the raw request body using the API key as the signing secret, sent in
 * the `x-tm-signature` header.
 */
class TextMagicProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsDynamicOptions
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://rest.textmagic.com/api/v2';

    public function getId(): string
    {
        return 'textmagic';
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
                    'description' => __('Your TextMagic account username.', 'wp-sms'),
                ],
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API v2 Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generate at My Settings → API → REST API keys.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID or Number', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Approved Sender ID, dedicated or shared number. Leave blank for the account default.', 'wp-sms'),
                        'dynamic'     => true,
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        // TODO(verify): TextMagic exposes a full Verify-as-a-Service lifecycle
        // (POST /verify, PUT /verify/{id}, DELETE /verify/{id}). Wire this once
        // WSMS introduces a SupportsVerify interface.

        $username = $this->getSharedConfig('username');
        $apiKey = $this->getSharedConfig('api_key');

        if (!$username || !$apiKey) {
            return DeliveryResult::failed(__('TextMagic credentials not configured', 'wp-sms'));
        }

        if ($message->getChannel() !== 'sms') {
            return DeliveryResult::failed(sprintf(
                __('TextMagic does not support channel %s', 'wp-sms'),
                $message->getChannel()
            ));
        }

        // TextMagic requires E.164 without the leading plus.
        $recipient = ltrim($message->getRecipient(), '+');

        $body = [
            'text'   => $message->getBody(),
            'phones' => $recipient,
        ];

        $from = $this->getChannelConfig('sms', 'from');
        if ($from) {
            $body['from'] = $from;
        }

        $result = $this->httpPost(self::API_BASE . '/messages', [
            'headers' => $this->authHeaders(),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true) ?: [];

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid TextMagic username or API key', 'wp-sms'));
        }

        if ($result['code'] === 201) {
            $providerId = $data['messageId'] ?? $data['id'] ?? null;
            return DeliveryResult::queued($providerId !== null ? (string) $providerId : null);
        }

        return DeliveryResult::failed(
            $data['message'] ?? "HTTP {$result['code']}",
            array_filter([
                'textmagic_status' => $data['status'] ?? null,
                'textmagic_code'   => $result['code'] ?: null,
            ]),
        );
    }

    public function getCredit(): ?string
    {
        $username = $this->getSharedConfig('username');
        $apiKey = $this->getSharedConfig('api_key');

        if (!$username || !$apiKey) {
            return null;
        }

        try {
            $data = $this->fetchJsonOrFail(self::API_BASE . '/user', [
                'headers' => $this->authHeaders(),
            ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (!isset($data['balance'])) {
            return null;
        }

        $symbol = $data['currency']['htmlSymbol']
            ?? $data['currency']['unicodeSymbol']
            ?? '';

        return $symbol . number_format((float) $data['balance'], 2);
    }

    public function testConnection(): TestConnectionResult
    {
        $username = $this->getSharedConfig('username');
        $apiKey = $this->getSharedConfig('api_key');

        if (!$username || !$apiKey) {
            return TestConnectionResult::error(__('Username and API key are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/ping', [
            'headers' => $this->authHeaders(),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid TextMagic username or API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'TextMagic');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = $this->getCredit();

        return TestConnectionResult::ok(
            $balance
                ? sprintf(__('Connected — Balance: %s', 'wp-sms'), $balance)
                : __('Connection successful.', 'wp-sms'),
            $balance ? ['balance' => $balance] : []
        );
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($section !== 'sms' || $fieldKey !== 'from') {
            return [];
        }

        return $this->withConfig($config, function () {
            try {
                $data = $this->fetchJsonOrFail(self::API_BASE . '/sources', [
                    'headers' => $this->authHeaders(),
                ]);
            } catch (\Throwable $e) {
                return [];
            }

            $options = [];
            foreach (['shared', 'dedicated', 'senderIds', 'user'] as $bucket) {
                foreach (($data[$bucket] ?? []) as $value) {
                    $value = (string) $value;
                    if ($value === '') {
                        continue;
                    }
                    $options[] = ['value' => $value, 'label' => $value];
                }
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
        return $this->verifySignature($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $body = $this->decodeBody($request);

        $messageId = $body['message_id'] ?? null;
        $rawStatus = (string) ($body['status'] ?? '');

        if (!$messageId || $rawStatus === '') {
            return [];
        }

        $normalized = $this->mapStatus($rawStatus);
        $permanent = in_array($normalized, ['failed', 'rejected'], true);

        return [new StatusUpdate(
            providerId:   (string) $messageId,
            status:       $normalized,
            errorCode:    $rawStatus,
            errorMessage: $normalized === 'failed' ? sprintf('TextMagic: %s', $rawStatus) : null,
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
        return $this->verifySignature($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $body = $this->decodeBody($request);

        $from = (string) ($body['from'] ?? '');
        if ($from === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($body['to'] ?? ''),
            body:       (string) ($body['text'] ?? ''),
            providerId: isset($body['message_id']) ? (string) $body['message_id'] : null,
            meta:       array_filter([
                'received_at' => $body['timestamp'] ?? null,
            ]),
        )];
    }

    // --- Internal ---

    private function authHeaders(): array
    {
        return [
            'X-TM-Username' => (string) $this->getSharedConfig('username'),
            'X-TM-Key'      => (string) $this->getSharedConfig('api_key'),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    /**
     * Verify the `x-tm-signature` header against the raw request body using the API key.
     * TextMagic signs both delivery and inbound webhooks the same way.
     */
    private function verifySignature(\WP_REST_Request $request): bool
    {
        $signature = $request->get_header('x-tm-signature');
        if (!$signature) {
            return false;
        }

        $apiKey = (string) $this->getSharedConfig('api_key');
        if ($apiKey === '') {
            return false;
        }

        $expected = hash_hmac('sha256', (string) $request->get_body(), $apiKey);
        return hash_equals($expected, $signature);
    }

    private function decodeBody(\WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        if (is_array($json) && !empty($json)) {
            return $json;
        }

        $params = $request->get_params();
        if (is_array($params) && !empty($params)) {
            return $params;
        }

        $decoded = json_decode((string) $request->get_body(), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function mapStatus(string $code): string
    {
        $code = strtolower($code);
        return match ($code) {
            'd', 'delivered'                  => 'delivered',
            's', 'sent', 'a', 'acked'         => 'sent',
            'q', 'queued', 'e', 'enroute'     => 'queued',
            'f', 'failed', 'r', 'rejected',
            'x', 'expired'                    => 'failed',
            default                           => 'queued',
        };
    }
}

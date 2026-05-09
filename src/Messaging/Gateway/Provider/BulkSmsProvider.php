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
 * BulkSMS — global SMS aggregator (https://www.bulksms.com/).
 *
 * Auth: HTTP Basic with API Token ID + Token Secret created in
 * Settings → Developer Settings → API Tokens. Body and webhooks are
 * JSON arrays of Message objects. BulkSMS does not sign callbacks;
 * their docs recommend a URL secret, validated here as ?token=...
 */
final class BulkSmsProvider extends AbstractProvider implements SupportsStatusCallback, SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.bulksms.com/v1';

    public function getId(): string
    {
        return 'bulksms';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'token_id' => [
                    'type'        => 'string',
                    'label'       => __('API Token ID', 'wp-sms'),
                    'required'    => true,
                    'description' => __('BulkSMS API Token ID from Settings → Developer Settings → API Tokens.', 'wp-sms'),
                ],
                'token_secret' => [
                    'type'        => 'secret',
                    'label'       => __('API Token Secret', 'wp-sms'),
                    'required'    => true,
                    'description' => __('BulkSMS API Token Secret. Used as the HTTP Basic password.', 'wp-sms'),
                ],
                'webhook_token' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Optional token appended as ?token=... to BulkSMS webhook URLs. BulkSMS does not sign callbacks, so this shared secret is the recommended way to authenticate them.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Optional alphanumeric (max 11 chars) or international number sender. Leave blank to use a BulkSMS repliable shared number so MO replies route back.', 'wp-sms'),
                        'placeholder' => '+15551234567',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!$this->hasCredentials()) {
            return DeliveryResult::failed(__('BulkSMS credentials not configured', 'wp-sms'));
        }

        $payload = [
            'to'   => $message->getRecipient(),
            'body' => $message->getBody(),
        ];

        $from = $this->getChannelConfig($message->getChannel(), 'from');
        $payload['from'] = $from !== null && $from !== ''
            ? (string) $from
            : ['type' => 'REPLIABLE'];

        $result = $this->httpPost(self::API_BASE . '/messages?auto-unicode=true', [
            'headers' => array_merge($this->authHeaders(), [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ]),
            'body' => wp_json_encode([$payload]),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid BulkSMS API Token ID or Secret', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] < 200 || $result['code'] >= 300) {
            $error = is_array($data)
                ? ($data['detail'] ?? $data['title'] ?? $data['error_description'] ?? sprintf('HTTP %d', $result['code']))
                : sprintf('HTTP %d', $result['code']);
            return DeliveryResult::failed((string) $error);
        }

        if (!is_array($data) || !isset($data[0])) {
            return DeliveryResult::failed(__('Invalid response from BulkSMS', 'wp-sms'));
        }

        $first = $data[0];
        $providerId = isset($first['id']) ? (string) $first['id'] : null;
        $statusType = strtoupper((string) ($first['status']['type'] ?? ''));

        if ($statusType === 'SENT') {
            return DeliveryResult::sent($providerId);
        }

        return DeliveryResult::queued($providerId);
    }

    public function getCredit(): ?string
    {
        if (!$this->hasCredentials()) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/profile', [
            'headers' => $this->authHeaders(),
        ]);

        if ($result instanceof DeliveryResult || $result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        $balance = is_array($data) ? ($data['credits']['balance'] ?? null) : null;

        return $balance === null ? null : (string) $balance;
    }

    public function testConnection(): TestConnectionResult
    {
        if (!$this->hasCredentials()) {
            return TestConnectionResult::error(__('API Token ID and Token Secret are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/profile', [
            'headers' => $this->authHeaders(),
        ]);

        if (!$result instanceof DeliveryResult && ($result['code'] === 401 || $result['code'] === 403)) {
            return TestConnectionResult::error(__('Invalid BulkSMS API Token ID or Secret', 'wp-sms'));
        }

        $data = $this->validateTestResponse($result, 'BulkSMS');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = $data['credits']['balance'] ?? null;
        $balanceLabel = $balance !== null ? (string) $balance : 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected to BulkSMS — Balance: %s', 'wp-sms'), $balanceLabel),
            $balance !== null ? ['balance' => $balance] : [],
        );
    }

    public function getStatusCallbackUrl(): string
    {
        return $this->callbackUrl('status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->validateWebhookToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $messages = $this->messages($request);
        $updates = [];

        foreach ($messages as $msg) {
            $providerId = isset($msg['id']) ? (string) $msg['id'] : '';
            if ($providerId === '') {
                continue;
            }

            $statusType = strtoupper((string) ($msg['status']['type'] ?? ''));
            $subtype = strtoupper((string) ($msg['status']['subtype'] ?? ''));

            $status = match ($statusType) {
                'DELIVERED'             => 'delivered',
                'FAILED'                => 'failed',
                'SENT'                  => 'sent',
                'ACCEPTED', 'SCHEDULED' => 'queued',
                default                 => 'sent',
            };

            $errorCode = $subtype !== '' ? $subtype : ($statusType !== '' ? $statusType : null);
            $permanent = $status === 'failed' && in_array($subtype, ['BLOCKED', 'NOT_SENT', 'HANDSET_ERROR'], true);

            $updates[] = new StatusUpdate(
                providerId:   $providerId,
                status:       $status,
                errorCode:    $errorCode,
                errorMessage: $status === 'failed' ? $this->failureMessage($subtype) : null,
                permanent:    $permanent,
                unsubscribe:  $status === 'failed' && $subtype === 'BLOCKED',
            );
        }

        return $updates;
    }

    public function getInboundCallbackUrl(): string
    {
        return $this->callbackUrl('inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->validateWebhookToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $messages = $this->messages($request);
        $inbound = [];

        foreach ($messages as $msg) {
            if (strtoupper((string) ($msg['type'] ?? '')) !== 'RECEIVED') {
                continue;
            }

            $from = (string) ($msg['from'] ?? '');
            if ($from === '') {
                continue;
            }

            $inbound[] = new InboundMessage(
                from:       $from,
                to:         (string) ($msg['to'] ?? ''),
                body:       (string) ($msg['body'] ?? ''),
                providerId: isset($msg['id']) ? (string) $msg['id'] : null,
                meta:       array_filter([
                    'related_sent_message_id' => $msg['relatedSentMessageId'] ?? null,
                    'submission'              => $msg['submission']['date'] ?? null,
                ]),
            );
        }

        return $inbound;
    }

    private function hasCredentials(): bool
    {
        return (bool) ($this->getSharedConfig('token_id') && $this->getSharedConfig('token_secret'));
    }

    private function authHeaders(): array
    {
        $tokenId = (string) $this->getSharedConfig('token_id');
        $tokenSecret = (string) $this->getSharedConfig('token_secret');

        return [
            'Authorization' => 'Basic ' . base64_encode($tokenId . ':' . $tokenSecret),
        ];
    }

    private function callbackUrl(string $type): string
    {
        $url = RestRoute::url('callbacks/' . $this->getId() . '/' . $type);
        $token = $this->getSharedConfig('webhook_token');

        if (!$token) {
            return $url;
        }

        return $url . '?token=' . rawurlencode((string) $token);
    }

    private function validateWebhookToken(\WP_REST_Request $request): bool
    {
        $token = $this->getSharedConfig('webhook_token');
        if (!$token) {
            return true;
        }

        $received = $request->get_param('token');
        return is_string($received) && hash_equals((string) $token, $received);
    }

    /** @return array<int, array<string, mixed>> */
    private function messages(\WP_REST_Request $request): array
    {
        $payload = $request->get_json_params();
        if (empty($payload)) {
            return [];
        }

        // BulkSMS posts an array of Message; tolerate a single-message object too.
        if (isset($payload[0]) && is_array($payload[0])) {
            return $payload;
        }
        if (isset($payload['id'])) {
            return [$payload];
        }

        return [];
    }

    private function failureMessage(string $subtype): string
    {
        return match ($subtype) {
            'BLOCKED'       => __('BulkSMS: recipient blocked (replied STOP)', 'wp-sms'),
            'NOT_SENT'      => __('BulkSMS: message not sent', 'wp-sms'),
            'HANDSET_ERROR' => __('BulkSMS: handset error', 'wp-sms'),
            'EXPIRED'       => __('BulkSMS: message expired before delivery', 'wp-sms'),
            ''              => __('BulkSMS: delivery failed', 'wp-sms'),
            default         => sprintf(__('BulkSMS: delivery failed (%s)', 'wp-sms'), $subtype),
        };
    }
}

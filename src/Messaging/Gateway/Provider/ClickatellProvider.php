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
 * Clickatell — One API for SMS + WhatsApp.
 *
 * Auth uses a raw API key in the Authorization header (no Bearer prefix), confirmed
 * against the v7 wp-sms-pro implementation and the One API "apiKey" auth scheme.
 *
 * Webhooks have no signature; we embed an operator-chosen token in the callback URL
 * and reject anything missing it (constant-time comparison).
 *
 * TODO(verify): Clickatell ships a separate Verify API (not part of One API);
 * defer until WSMS adds a SupportsVerify interface.
 * TODO(templates): WhatsApp send body accepts a `template` object;
 * wire via SupportsTemplates when WhatsApp template flow lands.
 */
class ClickatellProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsOptOutDetection
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const BASE_URL = 'https://platform.clickatell.com';
    private const PERMANENT_ERROR_CODES = [25, 26, 27, 44, 100, 110, 111];
    private const OPT_OUT_ERROR_CODES = [26];

    public function getId(): string
    {
        return 'clickatell';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Clickatell One API integration key from portal.clickatell.com.', 'wp-sms'),
                    'placeholder' => 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX==',
                ],
                'callback_token' => [
                    'type'        => 'secret',
                    'label'       => __('Callback Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('A random string you generate. WSMS appends it to the callback URLs and rejects callbacks without it.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('From Number', 'wp-sms'),
                        'required'    => false,
                        'placeholder' => '+15551234567',
                        'description' => __('Sender number assigned by Clickatell. Required for US two-way SMS.', 'wp-sms'),
                    ],
                ],
                'whatsapp' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('WhatsApp Sender', 'wp-sms'),
                        'required'    => false,
                        'placeholder' => '+15551234567',
                        'description' => __('Approved WhatsApp business number on your Clickatell integration.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('Clickatell API Key is not configured.', 'wp-sms'));
        }

        $channel = $message->getChannel();
        if (!in_array($channel, $this->getSupportedChannels(), true)) {
            return DeliveryResult::failed(sprintf(__('Channel %s is not supported by Clickatell.', 'wp-sms'), $channel));
        }

        $from = $this->getChannelConfig($channel, 'from');

        $payloadMessage = [
            'channel' => $channel,
            'to'      => $message->getRecipient(),
            'content' => $message->getBody(),
        ];
        if ($from) {
            $payloadMessage['from'] = $from;
        }

        $result = $this->httpPost(self::BASE_URL . '/v1/message', [
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode(['messages' => [$payloadMessage]]),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $code = $result['code'];
        $data = json_decode($result['body'], true);

        if ($code === 401 || $code === 403) {
            return DeliveryResult::failed(__('Invalid Clickatell API Key.', 'wp-sms'));
        }

        if ($code !== 202 && $code !== 207) {
            $globalErr = is_array($data) ? ($data['error']['description'] ?? null) : null;
            return DeliveryResult::failed($globalErr ?? sprintf('HTTP %d', $code));
        }

        $first = is_array($data) ? ($data['messages'][0] ?? null) : null;
        if (!$first || empty($first['accepted'])) {
            $errCode = $first['error']['code'] ?? null;
            $errMsg  = $first['error']['description'] ?? __('Clickatell rejected the message.', 'wp-sms');

            return DeliveryResult::failed(
                $errMsg,
                meta: array_filter(['provider_code' => $errCode], fn($v) => $v !== null),
                retryable: $errCode !== null && !in_array((int) $errCode, self::PERMANENT_ERROR_CODES, true),
            );
        }

        return DeliveryResult::queued($first['apiMessageId'] ?? null);
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpGet(self::BASE_URL . '/v1/balance', [
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => $apiKey,
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['balance'])) {
            return null;
        }

        $currency = $data['currency'] ?? 'USD';
        return number_format((float) $data['balance'], 2) . ' ' . $currency;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required.', 'wp-sms'));
        }

        $result = $this->httpGet(self::BASE_URL . '/v1/balance', [
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => $apiKey,
            ],
        ]);

        if (!($result instanceof DeliveryResult)) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Clickatell API Key.', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Clickatell');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (!isset($data['balance'])) {
            return TestConnectionResult::error(__('Unexpected response from Clickatell.', 'wp-sms'));
        }

        $balance  = number_format((float) $data['balance'], 2);
        $currency = $data['currency'] ?? 'USD';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s %s', 'wp-sms'), $balance, $currency),
            ['balance' => $balance, 'currency' => $currency],
        );
    }

    public function getStatusCallbackUrl(): string
    {
        return $this->callbackUrl('status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyCallbackToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $body = json_decode($request->get_body() ?: '[]', true);
        $updates = is_array($body) ? ($body['event']['messageStatusUpdate'] ?? []) : [];

        $out = [];
        foreach ($updates as $u) {
            $messageId = $u['messageId'] ?? null;
            if (!$messageId) {
                continue;
            }

            $statusCode = (int) ($u['statusCode'] ?? 0);
            $normalized = match ($statusCode) {
                2, 3       => 'queued',
                4          => 'sent',
                5, 6       => 'delivered',
                default    => 'failed',
            };

            $errorCode = $u['error']['code'] ?? null;
            $permanent = $normalized === 'failed' && $errorCode !== null
                && in_array((int) $errorCode, self::PERMANENT_ERROR_CODES, true);

            $out[] = new StatusUpdate(
                providerId:   (string) $messageId,
                status:       $normalized,
                errorCode:    $errorCode !== null ? (string) $errorCode : null,
                errorMessage: $u['error']['description'] ?? null,
                permanent:    $permanent,
            );
        }

        return $out;
    }

    public function getInboundCallbackUrl(): string
    {
        return $this->callbackUrl('inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyCallbackToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $body = json_decode($request->get_body() ?: '[]', true);
        $events = is_array($body) ? ($body['event']['moText'] ?? []) : [];

        $out = [];
        foreach ($events as $e) {
            $from = $e['from'] ?? null;
            if (!$from) {
                continue;
            }

            $out[] = new InboundMessage(
                from:       (string) $from,
                to:         (string) ($e['to'] ?? ''),
                body:       (string) ($e['content'] ?? ''),
                providerId: isset($e['messageId']) ? (string) $e['messageId'] : null,
                meta:       array_filter([
                    'channel'   => $e['channel'] ?? null,
                    'timestamp' => $e['timestamp'] ?? null,
                ]),
            );
        }

        return $out;
    }

    public function isOptOutError(DeliveryResult $result): bool
    {
        $code = $result->meta['provider_code'] ?? null;
        return $code !== null && in_array((int) $code, self::OPT_OUT_ERROR_CODES, true);
    }

    private function callbackUrl(string $type): string
    {
        $token = (string) $this->getSharedConfig('callback_token');
        $url = RestRoute::url('callbacks/' . $this->getId() . '/' . $type);
        if ($token === '') {
            return $url;
        }
        return $url . '?token=' . rawurlencode($token);
    }

    private function verifyCallbackToken(\WP_REST_Request $request): bool
    {
        $expected = (string) $this->getSharedConfig('callback_token');
        if ($expected === '') {
            return false;
        }
        $provided = (string) ($request->get_param('token') ?? '');
        return $provided !== '' && hash_equals($expected, $provided);
    }
}

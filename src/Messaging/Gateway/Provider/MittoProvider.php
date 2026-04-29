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

class MittoProvider extends AbstractProvider implements SupportsStatusCallback
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = true;

    private const API_BASE = 'https://rest.mittoapi.com/v2';

    public function getId(): string
    {
        return 'mitto';
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
                    'description' => __('The X-Mitto-API-Key value Mitto provides after onboarding (info@mitto.ch).', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Alphanumeric ID (3–11 chars) or numeric MSISDN (3–14 digits). Must be pre-cleared with Mitto for the destination market.', 'wp-sms'),
                    ],
                    'unicode' => [
                        'type'        => 'boolean',
                        'label'       => __('Force Unicode', 'wp-sms'),
                        'default'     => false,
                        'description' => __('Send as UCS-2 (type=Unicode). Leave off to send GSM-7 — Mitto will reject GSM-incompatible characters.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('Mitto API Key is not configured', 'wp-sms'));
        }

        $from = $this->getChannelConfig('sms', 'from');
        if (!$from) {
            return DeliveryResult::failed(__('Mitto Sender is not configured', 'wp-sms'));
        }

        $body = [
            'from'     => $from,
            'to'       => $message->getRecipient(),
            'text'     => $message->getBody(),
            'type'     => $this->getChannelConfig('sms', 'unicode') ? 'Unicode' : 'GSM',
            'callback' => $this->getStatusCallbackUrl(),
        ];

        $reference = $message->getFlowExecutionId() ?? $message->getCampaignId();
        if ($reference !== null && $reference !== '') {
            $body['reference'] = (string) $reference;
        }

        $result = $this->httpPost(self::API_BASE . '/sms.json', [
            'headers' => $this->authHeaders($apiKey),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401) {
            return DeliveryResult::failed(__('Invalid Mitto API Key', 'wp-sms'));
        }

        $data = json_decode($result['body'], true) ?: [];

        if ($result['code'] >= 200 && $result['code'] < 300 && (int) ($data['responseCode'] ?? -1) === 0) {
            return DeliveryResult::queued((string) ($data['id'] ?? ''));
        }

        return DeliveryResult::failed(
            $data['responseText'] ?? sprintf('HTTP %d', $result['code']),
            meta: array_filter([
                'mitto_response_code' => isset($data['responseCode']) ? (string) $data['responseCode'] : null,
                'mitto_http_code'     => $result['code'] ?: null,
            ]),
        );
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $from = $this->getChannelConfig('sms', 'from');
        if (!$from) {
            return TestConnectionResult::error(__('Sender is required to test the connection', 'wp-sms'));
        }

        // Mitto exposes no balance endpoint. The SMS API's `test: true` flag
        // auth-validates the request without dispatching a real SMS — it's
        // the only documented way to probe credentials.
        $result = $this->httpPost(self::API_BASE . '/sms.json', [
            'headers' => $this->authHeaders($apiKey),
            'body'    => wp_json_encode([
                'from' => $from,
                'to'   => '+15555550100',
                'text' => 'wsms test',
                'test' => true,
            ]),
        ]);

        if (!$result instanceof DeliveryResult && $result['code'] === 401) {
            return TestConnectionResult::error(__('Invalid Mitto API Key', 'wp-sms'));
        }

        $data = $this->validateTestResponse($result, 'Mitto');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if ((int) ($data['responseCode'] ?? -1) !== 0) {
            return TestConnectionResult::error(
                $data['responseText'] ?? __('Unexpected response from Mitto', 'wp-sms'),
            );
        }

        return TestConnectionResult::ok(__('Connection successful', 'wp-sms'));
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status', ['token' => $this->callbackToken()]);
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
        $msgId = (string) ($request->get_param('msgid') ?? '');
        $status = (string) ($request->get_param('status') ?? '');

        if ($msgId === '' || $status === '') {
            return [];
        }

        $normalized = match (strtoupper($status)) {
            'BUFFERED'                          => 'queued',
            'SENT'                              => 'sent',
            'DELIVERED'                         => 'delivered',
            'UNDELIVERED', 'FAILED', 'EXPIRED'  => 'failed',
            default                             => $status,
        };

        $errorCode = $request->get_param('errorcode');
        $errorCode = ($errorCode !== null && (string) $errorCode !== '' && (string) $errorCode !== '0')
            ? (string) $errorCode
            : null;

        return [new StatusUpdate(
            providerId:   $msgId,
            status:       $normalized,
            errorCode:    $errorCode,
            errorMessage: $normalized === 'failed' ? sprintf('Mitto DLR: %s', $status) : null,
            permanent:    in_array(strtoupper($status), ['UNDELIVERED', 'FAILED'], true),
        )];
    }

    // --- Internal ---

    private function authHeaders(string $apiKey): array
    {
        return [
            'X-Mitto-API-Key' => $apiKey,
            'Content-Type'    => 'application/json',
            'Accept'          => 'application/json',
        ];
    }

    private function callbackToken(): string
    {
        return hash_hmac('sha256', 'mitto-dlr', (string) $this->getSharedConfig('api_key'));
    }
}

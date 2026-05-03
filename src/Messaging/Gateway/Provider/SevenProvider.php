<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsInboundMessage;
use WSms\Messaging\Contracts\SupportsOptOutDetection;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\SupportsTemplates;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

/**
 * seven — SMS, Voice, RCS, and WhatsApp via gateway.seven.io.
 *
 * Auth: single `X-Api-Key` header for all four channels. Webhook callbacks
 * are not signed by seven.io; we authenticate them via a shared
 * `X-WSMS-Token` header that the admin sets on the webhook subscription
 * (the SDK exposes a free-form `headers` field on `POST /hooks` for this).
 *
 * Endpoints (per github.com/seven-io/php-client and seven-io/api-schemes):
 *  - POST /sms          (form-encoded: text, to, from, flash, foreign_id, label, …)
 *  - POST /voice        (form-encoded: text, to, from, ringtime)
 *  - POST /rcs/messages (form-encoded: text, to, from, foreign_id, label, …)
 *  - POST /waba/messages (form-encoded: from, to, type, text|template|url, …)
 *  - GET  /balance      → {amount, currency}
 *
 * Out of scope:
 *  - MMS via /sms (requires a separate /files upload step — not enough value yet).
 *  - Verify-as-a-Service (no first-class /verify endpoint; route via SupportsVerify
 *    when WSMS lands the interface).
 *  - Active-numbers dropdown (Numbers API exists but manual `from` entry is fine).
 *  - WhatsApp template *list* (Meta-managed; no provider list endpoint exists).
 */
class SevenProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsOptOutDetection,
    SupportsTemplates
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const BASE_URL = 'https://gateway.seven.io/api';

    /** seven.io API "success" code returned on accepted dispatch. */
    private const ACCEPTED_CODE = '100';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'seven';
    }

    public function getName(): string
    {
        return 'seven';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'voice', 'rcs', 'whatsapp'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Find it in your seven.io dashboard under Developer > API tokens.', 'wp-sms'),
                ],
                'webhook_token' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Auth Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Optional. Set this same value on seven.io\'s webhook subscription `headers` field as `X-WSMS-Token: <value>` to verify incoming callbacks.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => false,
                        'placeholder' => '+491701234567',
                        'description' => __('Sender ID — up to 11 alphanumeric or 16 numeric characters. Leave blank to use the seven.io account default.', 'wp-sms'),
                    ],
                ],
                'voice' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Caller ID', 'wp-sms'),
                        'required'    => false,
                        'placeholder' => '+491701234567',
                        'description' => __('Number that recipients see when the TTS call rings.', 'wp-sms'),
                    ],
                ],
                'rcs' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('RCS Agent ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Verified RCS Business agent ID registered with seven.io.', 'wp-sms'),
                    ],
                ],
                'whatsapp' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('WhatsApp Sender Number', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => '+491701234567',
                        'description' => __('WhatsApp Business sender registered under WhatsApp > Senders in your seven.io dashboard.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!$this->getSharedConfig('api_key')) {
            return DeliveryResult::failed(__('seven.io API key not configured', 'wp-sms'));
        }

        return match ($message->getChannel()) {
            'sms'      => $this->sendSms($message),
            'voice'    => $this->sendVoice($message),
            'rcs'      => $this->sendRcs($message),
            'whatsapp' => $this->sendWaba($message),
            default    => DeliveryResult::failed(sprintf(__('seven.io does not support channel %s', 'wp-sms'), $message->getChannel())),
        };
    }

    private function sendSms(MessageInterface $message): DeliveryResult
    {
        $meta = $message->getMeta();

        $payload = [
            'to'                   => $message->getRecipient(),
            'text'                 => $message->getBody(),
            'performance_tracking' => '0',
        ];

        $from = (string) $this->getChannelConfig('sms', 'from', '');
        if ($from !== '') {
            $payload['from'] = $from;
        }

        if (!empty($meta['flash'])) {
            $payload['flash'] = '1';
        }

        if (!empty($meta['foreign_id'])) {
            $payload['foreign_id'] = (string) $meta['foreign_id'];
        }

        if (!empty($meta['label'])) {
            $payload['label'] = (string) $meta['label'];
        }

        return $this->dispatch('/sms', $payload);
    }

    private function sendVoice(MessageInterface $message): DeliveryResult
    {
        $payload = [
            'to'   => $message->getRecipient(),
            'text' => $message->getBody(),
        ];

        $from = (string) $this->getChannelConfig('voice', 'from', '');
        if ($from !== '') {
            $payload['from'] = $from;
        }

        $meta = $message->getMeta();
        if (isset($meta['ringtime'])) {
            $payload['ringtime'] = (string) (int) $meta['ringtime'];
        }

        return $this->dispatch('/voice', $payload);
    }

    private function sendRcs(MessageInterface $message): DeliveryResult
    {
        $meta = $message->getMeta();

        $payload = [
            'to'                   => $message->getRecipient(),
            'text'                 => $message->getBody(),
            'performance_tracking' => '0',
        ];

        $from = (string) $this->getChannelConfig('rcs', 'from', '');
        if ($from !== '') {
            $payload['from'] = $from;
        }

        if (!empty($meta['foreign_id'])) {
            $payload['foreign_id'] = (string) $meta['foreign_id'];
        }

        if (!empty($meta['label'])) {
            $payload['label'] = (string) $meta['label'];
        }

        return $this->dispatch('/rcs/messages', $payload);
    }

    private function sendWaba(MessageInterface $message): DeliveryResult
    {
        $from = (string) $this->getChannelConfig('whatsapp', 'from', '');
        if ($from === '') {
            return DeliveryResult::failed(__('seven.io WhatsApp sender not configured', 'wp-sms'));
        }

        $meta = $message->getMeta();

        $payload = [
            'from' => $from,
            'to'   => $message->getRecipient(),
        ];

        $templatePayload = $this->resolveTemplatePayload($meta);
        if ($templatePayload !== null) {
            $payload = array_merge($payload, $templatePayload);
        } elseif (!empty($meta['media_urls'][0])) {
            $url = (string) $meta['media_urls'][0];
            $payload['type']    = $this->detectWabaMediaType($url);
            $payload['url']     = $url;
            $caption = (string) $message->getBody();
            if ($caption !== '') {
                $payload['caption'] = $caption;
            }
        } else {
            $payload['type'] = 'text';
            $payload['text'] = $message->getBody();
        }

        return $this->dispatch('/waba/messages', $payload);
    }

    /**
     * Issue a form-encoded POST and normalize the seven.io response shape into a DeliveryResult.
     */
    private function dispatch(string $path, array $payload): DeliveryResult
    {
        $result = $this->httpPost(self::BASE_URL . $path, [
            'headers' => $this->apiHeaders() + ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query($payload),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid seven.io API key', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] < 200 || $result['code'] >= 300) {
            $error = is_array($data) ? ($data['error'] ?? $data['message'] ?? null) : null;
            return DeliveryResult::failed($error ?: sprintf('HTTP %d', $result['code']));
        }

        if (!is_array($data)) {
            return DeliveryResult::failed(__('Invalid response from seven.io', 'wp-sms'));
        }

        $apiCode = isset($data['success']) ? (string) $data['success'] : '';
        $firstMessage = $data['messages'][0] ?? [];
        $providerId = isset($firstMessage['id']) ? (string) $firstMessage['id'] : null;

        if ($apiCode !== self::ACCEPTED_CODE) {
            $errorText = (string) ($firstMessage['error_text'] ?? $firstMessage['error'] ?? '');
            $message = $errorText !== '' ? sprintf('seven.io: %s', $errorText) : sprintf('seven.io error %s', $apiCode);
            return DeliveryResult::failed($message, array_filter([
                'seven_error_code' => $apiCode !== '' ? $apiCode : null,
                'seven_error_text' => $errorText !== '' ? $errorText : null,
            ]));
        }

        $perMessageOk = $firstMessage === [] || ($firstMessage['success'] ?? true);
        if (!$perMessageOk) {
            $errorText = (string) ($firstMessage['error_text'] ?? $firstMessage['error'] ?? '');
            return DeliveryResult::failed(
                $errorText !== '' ? sprintf('seven.io: %s', $errorText) : __('seven.io rejected the recipient', 'wp-sms'),
                array_filter([
                    'seven_error_code' => isset($firstMessage['error']) ? (string) $firstMessage['error'] : null,
                    'seven_error_text' => $errorText !== '' ? $errorText : null,
                ]),
            );
        }

        $cost = isset($data['total_price']) ? (float) $data['total_price'] : null;
        return DeliveryResult::queued($providerId, $cost);
    }

    public function getCredit(): ?string
    {
        if (!$this->getSharedConfig('api_key')) {
            return null;
        }

        $result = $this->httpGet(self::BASE_URL . '/balance', [
            'headers' => $this->apiHeaders(),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['amount'])) {
            return null;
        }

        $amount   = number_format((float) $data['amount'], 2);
        $currency = (string) ($data['currency'] ?? '');

        return $currency !== '' ? sprintf('%s %s', $amount, $currency) : $amount;
    }

    public function testConnection(): TestConnectionResult
    {
        if (!$this->getSharedConfig('api_key')) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::BASE_URL . '/balance', [
            'headers' => $this->apiHeaders(),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid seven.io API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'seven.io');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (!isset($data['amount'])) {
            return TestConnectionResult::ok(__('Connected to seven.io', 'wp-sms'));
        }

        $balance = number_format((float) $data['amount'], 2) . ' ' . (string) ($data['currency'] ?? '');
        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s', 'wp-sms'), trim($balance)),
            ['balance' => trim($balance)],
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
        $payload = $this->callbackPayload($request);
        $eventType = (string) ($payload['event_type'] ?? '');

        return match ($eventType) {
            'dlr', 'voice_status', 'rcs' => $this->buildStatusUpdates($payload),
            default                       => [],
        };
    }

    /** @return StatusUpdate[] */
    private function buildStatusUpdates(array $payload): array
    {
        $providerId = (string) ($payload['msg_id'] ?? $payload['id'] ?? '');
        $rawStatus  = strtoupper((string) ($payload['status'] ?? ''));
        if ($providerId === '' || $rawStatus === '') {
            return [];
        }

        $normalized = match ($rawStatus) {
            'ACCEPTED', 'BUFFERED', 'TRANSMITTED' => 'queued',
            'DELIVERED'                            => 'delivered',
            'FAILED', 'REJECTED', 'EXPIRED', 'NOTDELIVERED' => 'failed',
            default                                => strtolower($rawStatus),
        };

        $description = (string) ($payload['description'] ?? '');
        $errorCode   = isset($payload['error_code']) ? (string) $payload['error_code'] : null;

        return [new StatusUpdate(
            providerId:   $providerId,
            status:       $normalized,
            errorCode:    $errorCode,
            errorMessage: $normalized === 'failed' ? sprintf('seven.io: %s', $description !== '' ? $description : $rawStatus) : null,
            permanent:    in_array($rawStatus, ['FAILED', 'REJECTED', 'EXPIRED', 'NOTDELIVERED'], true),
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
        $payload = $this->callbackPayload($request);
        $eventType = (string) ($payload['event_type'] ?? '');

        return match ($eventType) {
            'sms_mo'      => $this->buildSmsInbound($payload),
            'voice_call'  => $this->buildVoiceInbound($payload),
            'rcs'         => $this->buildRcsInbound($payload),
            default       => [],
        };
    }

    /** @return InboundMessage[] */
    private function buildSmsInbound(array $payload): array
    {
        $from = (string) ($payload['sender'] ?? '');
        if ($from === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($payload['system'] ?? ''),
            body:       (string) ($payload['text'] ?? ''),
            providerId: isset($payload['id']) ? (string) $payload['id'] : null,
            meta:       array_filter([
                'received_at' => $payload['received_at'] ?? null,
            ]),
        )];
    }

    /** @return InboundMessage[] */
    private function buildVoiceInbound(array $payload): array
    {
        $from = (string) ($payload['caller'] ?? '');
        if ($from === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($payload['called'] ?? ''),
            body:       '',
            providerId: isset($payload['id']) ? (string) $payload['id'] : null,
            meta:       array_filter([
                'event' => 'voice_call',
            ]),
        )];
    }

    /** @return InboundMessage[] */
    private function buildRcsInbound(array $payload): array
    {
        $from = (string) ($payload['sender'] ?? $payload['from'] ?? '');
        if ($from === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($payload['system'] ?? $payload['to'] ?? ''),
            body:       (string) ($payload['text'] ?? ''),
            providerId: isset($payload['id']) ? (string) $payload['id'] : null,
            meta:       array_filter([
                'channel' => 'rcs',
            ]),
        )];
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        // seven.io does not document a dedicated opt-out error code. Be conservative:
        // only treat error_text containing explicit BLOCKED / OPT_OUT markers as opt-out.
        $errorText = (string) ($result->meta['seven_error_text'] ?? '');
        if ($errorText === '') {
            return false;
        }
        $upper = strtoupper($errorText);
        return str_contains($upper, 'OPT_OUT') || str_contains($upper, 'BLOCKED');
    }

    // --- SupportsTemplates ---

    public function requiresTemplateForChannel(string $channel): bool
    {
        // WhatsApp 24h-window replies are valid as freetext; only business-initiated
        // sends outside the window need a template. Don't force it here.
        return false;
    }

    public function getVariableStyle(): VariableStyle
    {
        return VariableStyle::Positional;
    }

    public function buildTemplatePayload(TemplateMapping $mapping, array $resolvedVariables): array
    {
        ksort($resolvedVariables, SORT_NATURAL);

        $bodyParameters = [];
        foreach ($resolvedVariables as $value) {
            $bodyParameters[] = ['type' => 'text', 'text' => (string) $value];
        }

        $components = [['type' => 'body', 'parameters' => $bodyParameters]];

        return [
            'type'       => 'template',
            'template'   => $mapping->providerTemplateId,
            'language'   => $mapping->language ?: 'en',
            'components' => wp_json_encode($components),
        ];
    }

    private function resolveTemplatePayload(array $meta): ?array
    {
        if (!empty($meta['template_mode']) && !empty($meta['provider_template_id'])) {
            $mapping = new TemplateMapping(
                templateType: '',
                providerTemplateId: (string) $meta['provider_template_id'],
                gatewayId: $this->getId(),
                language: (string) ($meta['template_language'] ?? 'en'),
                variableMap: [],
            );
            return $this->buildTemplatePayload($mapping, $meta['template_variables'] ?? []);
        }

        $templateType = $meta['template_type'] ?? null;
        if ($templateType && $this->catalogManager) {
            $mapping = $this->catalogManager->resolveMapping($templateType, $this->getId());
            if ($mapping) {
                $resolved = $mapping->resolveVariables($meta['template_variables'] ?? []);
                return $this->buildTemplatePayload($mapping, $resolved);
            }
        }

        return null;
    }

    // --- Internal ---

    private function apiHeaders(): array
    {
        return [
            'X-Api-Key' => (string) $this->getSharedConfig('api_key', ''),
            'Accept'    => 'application/json',
        ];
    }

    /**
     * Verify the X-WSMS-Token header against the configured webhook_token.
     *
     * seven.io webhook subscriptions allow a free-form `headers` field that is
     * echoed on each callback. Admins set `X-WSMS-Token: <token>` there, and we
     * compare it server-side. If no token is configured, reject — refusing
     * unauthenticated callbacks is safer than trusting them.
     */
    private function verifyToken(\WP_REST_Request $request): bool
    {
        $expected = (string) $this->getSharedConfig('webhook_token', '');
        if ($expected === '') {
            return false;
        }

        $supplied = (string) ($request->get_header('x-wsms-token') ?? '');
        if ($supplied === '') {
            return false;
        }

        return hash_equals($expected, $supplied);
    }

    /**
     * Pull the callback payload from JSON body or fall back to form params.
     *
     * @return array<string,mixed>
     */
    private function callbackPayload(\WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        if (is_array($json) && $json !== []) {
            return $json;
        }
        $params = $request->get_params();
        return is_array($params) ? $params : [];
    }

    private function detectWabaMediaType(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'mp4', 'mov', '3gp'                            => 'video',
            'mp3', 'ogg', 'amr', 'aac', 'm4a'              => 'audio',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt',
            'pptx', 'txt', 'csv'                           => 'document',
            'webp'                                         => 'sticker',
            default                                        => 'image',
        };
    }
}

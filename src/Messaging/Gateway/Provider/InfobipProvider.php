<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\ProviderTemplate;
use WSms\Messaging\Catalog\TemplateCatalogException;
use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\TemplateStatus;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsInboundMessage;
use WSms\Messaging\Contracts\SupportsOptOutDetection;
use WSms\Messaging\Contracts\SupportsRegulatoryIds;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\SupportsTemplateFetch;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

/**
 * Infobip — multi-channel CPaaS (SMS, WhatsApp, RCS, Email).
 *
 * Auth: `Authorization: App {api_key}` header against a per-account base URL
 * (e.g. https://xxxxx.api.infobip.com — found in dashboard → Developer Tools → API).
 *
 * Webhooks: Infobip does NOT sign webhook payloads. Their official SDKs ship no
 * verification helper; the documented security model is IP allowlist + HTTPS,
 * optionally with a URL token. We accept callbacks only when a configured
 * `webhook_token` matches the `?token=…` query param (constant-time compare).
 * If the operator leaves `webhook_token` empty, callbacks are rejected outright —
 * forcing an explicit opt-in is safer than silently accepting unauthenticated
 * webhooks.
 *
 * Status payload shape (all channels): `{ results: [ { messageId, status: { groupId, name }, … } ] }`.
 * Group IDs: 1=PENDING, 2=UNDELIVERABLE (permanent), 3=DELIVERED, 4=EXPIRED, 5=REJECTED (permanent).
 */
class InfobipProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsOptOutDetection,
    SupportsTemplateFetch,
    SupportsRegulatoryIds
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'infobip';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp', 'rcs', 'email'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'base_url' => [
                    'type'        => 'string',
                    'label'       => __('Base URL', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Account-specific Infobip API host (Developer Tools → API → Base API URL).', 'wp-sms'),
                    'placeholder' => 'xxxxx.api.infobip.com',
                ],
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('API key with permissions for the channels you plan to use. Sent as Authorization: App {key}.', 'wp-sms'),
                ],
                'webhook_token' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Random string appended to the callback URL as ?token=… and required on every incoming webhook. Infobip does not sign callbacks; combine this with their IP allowlist for defense-in-depth.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('From', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Alphanumeric sender ID or E.164 phone number.', 'wp-sms'),
                        'placeholder' => 'WSMSAlerts',
                    ],
                    'dlt_principal_entity_id' => [
                        'type'        => 'string',
                        'label'       => __('India DLT Principal Entity ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Required by TRAI for SMS to Indian numbers. Leave blank otherwise.', 'wp-sms'),
                    ],
                ],
                'whatsapp' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('WhatsApp Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('WhatsApp Business sender registered under your WABA (Meta-verified).', 'wp-sms'),
                        'placeholder' => '447860099299',
                    ],
                ],
                'rcs' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('RCS Agent ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('RCS Agent identifier configured under your Google RCS Business Messaging account.', 'wp-sms'),
                    ],
                ],
                'email' => [
                    'from_email' => [
                        'type'        => 'string',
                        'label'       => __('From Email', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Sender address from a domain you have verified in Infobip.', 'wp-sms'),
                        'placeholder' => 'noreply@yourdomain.com',
                    ],
                    'from_name' => [
                        'type'        => 'string',
                        'label'       => __('From Name', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Sender display name shown in the recipient inbox.', 'wp-sms'),
                        'placeholder' => 'Your Site Name',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        $baseUrl = $this->normalizedBaseUrl();

        if (!$apiKey || !$baseUrl) {
            return DeliveryResult::failed(__('Infobip credentials not configured', 'wp-sms'));
        }

        $channel = $message->getChannel();
        return match ($channel) {
            'sms'      => $this->sendSms($message, $apiKey, $baseUrl),
            'whatsapp' => $this->sendWhatsapp($message, $apiKey, $baseUrl),
            'rcs'      => $this->sendRcs($message, $apiKey, $baseUrl),
            'email'    => $this->sendEmail($message, $apiKey, $baseUrl),
            default    => DeliveryResult::failed(sprintf(__('Infobip does not support channel %s', 'wp-sms'), $channel)),
        };
    }

    private function sendSms(MessageInterface $message, string $apiKey, string $baseUrl): DeliveryResult
    {
        $from = $this->getChannelConfig('sms', 'from');
        if (!$from) {
            return DeliveryResult::failed(__('Infobip SMS sender not configured', 'wp-sms'));
        }

        $meta = $message->getMeta();
        $destination = ['to' => $message->getRecipient()];
        $mediaUrls = $meta['media_urls'] ?? [];
        if (!empty($mediaUrls)) {
            $destination['mediaUrl'] = array_values($mediaUrls);
        }

        $sms = [
            'from'         => $from,
            'destinations' => [$destination],
            'text'         => $message->getBody(),
            'notifyUrl'    => $this->getStatusCallbackUrl(),
        ];

        if (!empty($meta['flash'])) {
            $sms['flash'] = true;
        }

        $regulatory = $this->buildSmsRegulatoryPayload($meta);
        if (!empty($regulatory)) {
            $sms = array_merge($sms, $regulatory);
        }

        $body = ['messages' => [$sms]];

        $result = $this->httpPost(
            $baseUrl . '/sms/2/text/advanced',
            [
                'headers' => $this->jsonHeaders($apiKey),
                'body'    => wp_json_encode($body),
            ],
        );

        return $this->handleSendResponse($result, fn(array $data) => $data['messages'][0] ?? null);
    }

    private function sendWhatsapp(MessageInterface $message, string $apiKey, string $baseUrl): DeliveryResult
    {
        $from = $this->getChannelConfig('whatsapp', 'from');
        if (!$from) {
            return DeliveryResult::failed(__('Infobip WhatsApp sender not configured', 'wp-sms'));
        }

        $meta = $message->getMeta();
        $headers = $this->jsonHeaders($apiKey);

        $templatePayload = $this->resolveTemplatePayload($meta);
        if ($templatePayload !== null) {
            $body = [
                'messages' => [
                    array_merge(
                        [
                            'from'      => $from,
                            'to'        => $message->getRecipient(),
                            'notifyUrl' => $this->getStatusCallbackUrl(),
                        ],
                        $templatePayload,
                    ),
                ],
            ];
            $url = $baseUrl . '/whatsapp/1/message/template';
        } else {
            $mediaUrls = $meta['media_urls'] ?? [];
            if (!empty($mediaUrls)) {
                $mediaType = $meta['media_type'] ?? 'image';
                $endpoint = match ($mediaType) {
                    'image', 'document', 'video', 'audio' => $mediaType,
                    default => 'image',
                };
                $body = [
                    'from'      => $from,
                    'to'        => $message->getRecipient(),
                    'content'   => array_filter([
                        'mediaUrl' => $mediaUrls[0],
                        'caption'  => $message->getBody() ?: null,
                    ]),
                    'notifyUrl' => $this->getStatusCallbackUrl(),
                ];
                $url = $baseUrl . '/whatsapp/1/message/' . $endpoint;
            } else {
                $body = [
                    'from'      => $from,
                    'to'        => $message->getRecipient(),
                    'content'   => ['text' => $message->getBody()],
                    'notifyUrl' => $this->getStatusCallbackUrl(),
                ];
                $url = $baseUrl . '/whatsapp/1/message/text';
            }
        }

        $result = $this->httpPost($url, [
            'headers' => $headers,
            'body'    => wp_json_encode($body),
        ]);

        return $this->handleSendResponse($result, function (array $data) {
            return $data['messages'][0] ?? $data;
        });
    }

    private function sendRcs(MessageInterface $message, string $apiKey, string $baseUrl): DeliveryResult
    {
        $from = $this->getChannelConfig('rcs', 'from');
        if (!$from) {
            return DeliveryResult::failed(__('Infobip RCS Agent ID not configured', 'wp-sms'));
        }

        $meta = $message->getMeta();
        $mediaUrls = $meta['media_urls'] ?? [];

        if (!empty($mediaUrls)) {
            $content = [
                'type' => 'FILE',
                'file' => ['url' => $mediaUrls[0]],
            ];
        } else {
            $content = [
                'type' => 'TEXT',
                'text' => $message->getBody(),
            ];
        }

        $body = [
            'from'      => $from,
            'to'        => $message->getRecipient(),
            'content'   => $content,
            'notifyUrl' => $this->getStatusCallbackUrl(),
        ];

        $result = $this->httpPost(
            $baseUrl . '/rcs/3/message',
            [
                'headers' => $this->jsonHeaders($apiKey),
                'body'    => wp_json_encode($body),
            ],
        );

        return $this->handleSendResponse($result, fn(array $data) => $data);
    }

    private function sendEmail(MessageInterface $message, string $apiKey, string $baseUrl): DeliveryResult
    {
        $fromEmail = $this->getChannelConfig('email', 'from_email');
        if (!$fromEmail) {
            return DeliveryResult::failed(__('Infobip email sender not configured', 'wp-sms'));
        }

        $fromName = $this->getChannelConfig('email', 'from_name');
        $from = $fromName ? sprintf('%s <%s>', $fromName, $fromEmail) : $fromEmail;

        $meta = $message->getMeta();
        $body = $message->getBody();

        // Form-encoded body — Infobip's /email/3/send accepts multipart and
        // application/x-www-form-urlencoded. WordPress sends form-encoded when
        // body is a PHP array (and we omit Content-Type), which avoids manual
        // multipart boundary handling for the no-attachment path.
        $payload = array_filter([
            'from'         => $from,
            'to'           => $message->getRecipient(),
            'subject'      => $meta['subject'] ?? '',
            'html'         => $body,
            'text'         => strip_tags($body),
            'notifyUrl'    => $this->getStatusCallbackUrl(),
            'callbackData' => $meta['callback_data'] ?? null,
        ], static fn($v) => $v !== null && $v !== '');

        $result = $this->httpPost(
            $baseUrl . '/email/3/send',
            [
                'headers' => [
                    'Authorization' => 'App ' . $apiKey,
                    'Accept'        => 'application/json',
                ],
                'body'    => $payload,
            ],
        );

        return $this->handleSendResponse($result, fn(array $data) => $data['messages'][0] ?? $data);
    }

    /**
     * @param array{response: array, body: string, code: int}|DeliveryResult $result
     * @param callable(array): array|null                                    $extractMessage Returns the per-message slice from the decoded response
     */
    private function handleSendResponse(array|DeliveryResult $result, callable $extractMessage): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Infobip API key or base URL', 'wp-sms'));
        }

        if ($result['code'] === 429) {
            return DeliveryResult::failed(__('Rate limited by Infobip', 'wp-sms'), retryable: true);
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            $error = is_array($data) ? ($data['requestError']['serviceException']['text'] ?? null) : null;
            return DeliveryResult::failed(
                $error ?? sprintf('Infobip HTTP %d', $result['code']),
            );
        }

        if (!is_array($data)) {
            return DeliveryResult::failed(__('Invalid response from Infobip', 'wp-sms'));
        }

        $messageBlock = $extractMessage($data);
        if (!is_array($messageBlock)) {
            $messageBlock = $data;
        }

        $messageId = $messageBlock['messageId']
            ?? $messageBlock['bulkId']
            ?? $data['bulkId']
            ?? null;
        $status = $messageBlock['status'] ?? null;
        $groupId = is_array($status) ? (int) ($status['groupId'] ?? 1) : 1;
        $statusName = is_array($status) ? (string) ($status['name'] ?? '') : '';
        $statusDescription = is_array($status) ? (string) ($status['description'] ?? '') : '';

        $cost = null;
        if (isset($messageBlock['price']['pricePerMessage'])) {
            $cost = (float) $messageBlock['price']['pricePerMessage'];
        }

        return match ($groupId) {
            self::GROUP_PENDING                  => DeliveryResult::queued($messageId ? (string) $messageId : null),
            self::GROUP_DELIVERED                => DeliveryResult::sent($messageId ? (string) $messageId : null, $cost),
            self::GROUP_UNDELIVERABLE,
            self::GROUP_EXPIRED,
            self::GROUP_REJECTED                 => DeliveryResult::failed(
                $statusDescription !== '' ? $statusDescription : ($statusName !== '' ? $statusName : sprintf('Infobip group %d', $groupId)),
                meta: array_filter([
                    'infobip_group_id'    => $groupId,
                    'infobip_status_name' => $statusName ?: null,
                ]),
                retryable: $groupId === self::GROUP_EXPIRED,
            ),
            default => DeliveryResult::queued($messageId ? (string) $messageId : null),
        };
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        $baseUrl = $this->normalizedBaseUrl();

        if (!$apiKey || !$baseUrl) {
            return null;
        }

        $result = $this->httpGet($baseUrl . '/account/1/balance', [
            'headers' => $this->jsonHeaders($apiKey),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['balance'])) {
            return null;
        }

        return number_format((float) $data['balance'], 2) . ' ' . ((string) ($data['currency'] ?? ''));
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        $baseUrl = $this->normalizedBaseUrl();

        if (!$apiKey || !$baseUrl) {
            return TestConnectionResult::error(__('Base URL and API Key are required', 'wp-sms'));
        }

        $result = $this->httpGet($baseUrl . '/account/1/balance', [
            'headers' => $this->jsonHeaders($apiKey),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid API Key or Base URL', 'wp-sms'));
            }
            if ($result['code'] === 404) {
                return TestConnectionResult::error(__('Base URL not recognized — copy the value from Developer Tools → API.', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Infobip');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = $data['balance'] ?? null;
        $currency = (string) ($data['currency'] ?? '');
        if ($balance !== null) {
            return TestConnectionResult::ok(
                sprintf(__('Connected — Balance: %1$s %2$s', 'wp-sms'), number_format((float) $balance, 2), $currency),
                ['balance' => $balance, 'currency' => $currency],
            );
        }

        return TestConnectionResult::ok(__('Connected to Infobip', 'wp-sms'));
    }

    // --- SupportsStatusCallback ---

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
        $params = $request->get_json_params() ?: $request->get_params();
        $results = $params['results'] ?? [];
        if (!is_array($results)) {
            return [];
        }

        $updates = [];
        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }

            $messageId = (string) ($result['messageId'] ?? $result['bulkId'] ?? '');
            if ($messageId === '') {
                continue;
            }

            $status = $result['status'] ?? [];
            $groupId = (int) ($status['groupId'] ?? 0);
            $statusName = (string) ($status['name'] ?? '');
            $description = (string) ($status['description'] ?? '');
            $error = $result['error'] ?? [];
            $errorCode = isset($error['id']) ? (string) $error['id'] : null;
            $errorName = (string) ($error['name'] ?? '');

            $normalized = match ($groupId) {
                self::GROUP_PENDING       => 'queued',
                self::GROUP_DELIVERED     => 'delivered',
                self::GROUP_UNDELIVERABLE,
                self::GROUP_EXPIRED,
                self::GROUP_REJECTED      => 'failed',
                default                   => 'queued',
            };

            $isOptOut = $groupId === self::GROUP_REJECTED && $this->looksLikeOptOut($statusName . ' ' . $errorName);

            $updates[] = new StatusUpdate(
                providerId:   $messageId,
                status:       $normalized,
                errorCode:    $errorCode ?? ($statusName !== '' ? $statusName : null),
                errorMessage: $normalized === 'failed' && $description !== '' ? sprintf('Infobip: %s', $description) : null,
                permanent:    in_array($groupId, [self::GROUP_UNDELIVERABLE, self::GROUP_REJECTED], true),
                unsubscribe:  $isOptOut,
            );
        }

        return $updates;
    }

    // --- SupportsInboundMessage ---

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
        $params = $request->get_json_params() ?: $request->get_params();
        $results = $params['results'] ?? [];
        if (!is_array($results) || empty($results)) {
            return [];
        }

        $messages = [];
        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }

            $from = (string) ($result['from'] ?? '');
            if ($from === '') {
                continue;
            }

            // SMS / Email shape: { text, ... }. WhatsApp / RCS shape: { message: { text } }.
            $text = (string) (
                $result['text']
                ?? $result['message']['text']
                ?? $result['cleanText']
                ?? ''
            );

            $optOutType = $this->looksLikeOptOut($text) ? 'sms_stop' : null;

            $messages[] = new InboundMessage(
                from:       $from,
                to:         (string) ($result['to'] ?? ''),
                body:       $text,
                providerId: isset($result['messageId']) ? (string) $result['messageId'] : null,
                optOutType: $optOutType,
                meta:       array_filter([
                    'received_at' => $result['receivedAt'] ?? null,
                    'sms_count'   => $result['smsCount'] ?? null,
                    'callback'    => $result['callbackData'] ?? null,
                ]),
            );
        }

        return $messages;
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        $groupId = $result->meta['infobip_group_id'] ?? null;
        $statusName = (string) ($result->meta['infobip_status_name'] ?? '');

        if ((int) $groupId !== self::GROUP_REJECTED) {
            return false;
        }

        return $this->looksLikeOptOut($statusName);
    }

    // --- SupportsTemplates / SupportsTemplateFetch ---

    public function requiresTemplateForChannel(string $channel): bool
    {
        // WhatsApp business-initiated messages need approved templates, but free-form
        // replies inside the 24-hour customer-service window do not. Don't force-fail.
        return false;
    }

    public function getVariableStyle(): VariableStyle
    {
        return VariableStyle::Positional;
    }

    public function buildTemplatePayload(TemplateMapping $mapping, array $resolvedVariables): array
    {
        ksort($resolvedVariables, SORT_NATURAL);
        $placeholders = array_values(array_map('strval', $resolvedVariables));

        return [
            'content' => array_filter([
                'templateName' => $mapping->providerTemplateId,
                'language'     => $mapping->language ?: null,
                'templateData' => [
                    'body' => ['placeholders' => $placeholders],
                ],
            ]),
        ];
    }

    /** @return ProviderTemplate[] */
    public function fetchTemplates(): array
    {
        $apiKey = $this->getSharedConfig('api_key');
        $baseUrl = $this->normalizedBaseUrl();
        $sender = $this->getChannelConfig('whatsapp', 'from');

        if (!$apiKey || !$baseUrl) {
            throw new TemplateCatalogException(__('Infobip credentials not configured', 'wp-sms'));
        }
        if (!$sender) {
            throw new TemplateCatalogException(__('Configure the WhatsApp sender before fetching templates', 'wp-sms'));
        }

        $url = $baseUrl . '/whatsapp/2/senders/' . rawurlencode($sender) . '/templates';

        try {
            $data = $this->fetchJsonOrFail($url, ['headers' => $this->jsonHeaders($apiKey)]);
        } catch (\RuntimeException $e) {
            throw new TemplateCatalogException($e->getMessage(), 0, $e);
        }

        $templates = [];
        foreach ($data['templates'] ?? [] as $tpl) {
            if (!is_array($tpl)) {
                continue;
            }
            $template = $this->parseInfobipTemplate($tpl);
            if ($template && $template->isUsable()) {
                $templates[] = $template;
            }
        }

        return $templates;
    }

    // --- SupportsRegulatoryIds ---

    public function getRegulatoryConfigSchema(): array
    {
        return [
            'principal_entity_id' => [
                'type'        => 'string',
                'label'       => __('Principal Entity ID (DLT)', 'wp-sms'),
                'required'    => false,
                'description' => __('TRAI/DLT identifier required when sending to Indian numbers.', 'wp-sms'),
            ],
        ];
    }

    public function buildRegulatoryPayload(array $regulatoryMeta): array
    {
        $entityId = $regulatoryMeta['principal_entity_id'] ?? '';
        if ($entityId === '') {
            return [];
        }

        return [
            'regional' => [
                'indiaDlt' => array_filter([
                    'principalEntityId' => (string) $entityId,
                    'contentTemplateId' => $regulatoryMeta['content_template_id'] ?? null,
                ]),
            ],
        ];
    }

    // --- Internal ---

    private const GROUP_PENDING = 1;
    private const GROUP_UNDELIVERABLE = 2;
    private const GROUP_DELIVERED = 3;
    private const GROUP_EXPIRED = 4;
    private const GROUP_REJECTED = 5;

    private function jsonHeaders(string $apiKey): array
    {
        return [
            'Authorization' => 'App ' . $apiKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    private function normalizedBaseUrl(): string
    {
        $raw = trim((string) $this->getSharedConfig('base_url', ''));
        if ($raw === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $raw)) {
            $raw = 'https://' . $raw;
        }

        return rtrim($raw, '/');
    }

    private function callbackUrl(string $suffix): string
    {
        $token = $this->getSharedConfig('webhook_token');
        $args = $token ? ['token' => $token] : [];
        return RestRoute::url('callbacks/' . $this->getId() . '/' . $suffix, $args);
    }

    private function validateWebhookToken(\WP_REST_Request $request): bool
    {
        $expected = (string) $this->getSharedConfig('webhook_token', '');
        if ($expected === '') {
            // Operator hasn't opted in to token validation. Reject — refuse to
            // accept unauthenticated webhooks even if Infobip's IP allowlist
            // would have caught them at the network layer.
            return false;
        }

        $provided = (string) ($request->get_param('token') ?? '');
        if ($provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
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

    private function buildSmsRegulatoryPayload(array $meta): array
    {
        $regulatoryMeta = $meta['regulatory'] ?? [];
        if (empty($regulatoryMeta['principal_entity_id'])) {
            $configEntityId = (string) $this->getChannelConfig('sms', 'dlt_principal_entity_id', '');
            if ($configEntityId !== '') {
                $regulatoryMeta = array_merge(
                    ['principal_entity_id' => $configEntityId],
                    is_array($regulatoryMeta) ? $regulatoryMeta : [],
                );
            }
        }

        return is_array($regulatoryMeta) ? $this->buildRegulatoryPayload($regulatoryMeta) : [];
    }

    private function looksLikeOptOut(string $haystack): bool
    {
        $needle = strtoupper($haystack);
        if ($needle === '') {
            return false;
        }

        foreach (['STOP', 'UNSUBSCRIBE', 'UNSUB', 'STOPALL', 'BLACKLIST', 'BLOCKED', 'OPTED_OUT', 'OPTOUT'] as $keyword) {
            if (str_contains($needle, $keyword)) {
                return true;
            }
        }
        return false;
    }

    private function parseInfobipTemplate(array $tpl): ?ProviderTemplate
    {
        $name = (string) ($tpl['name'] ?? '');
        if ($name === '') {
            return null;
        }

        $language = (string) ($tpl['language'] ?? 'en');
        $statusRaw = (string) ($tpl['status'] ?? 'PENDING');
        $category = strtolower((string) ($tpl['category'] ?? 'utility'));

        $bodyText = (string) (
            $tpl['structure']['body']['text']
            ?? $tpl['body']
            ?? ''
        );

        $variableCount = preg_match_all('/\{\{\s*\d+\s*\}\}/', $bodyText) ?: 0;

        return new ProviderTemplate(
            id:            $name,
            name:          $name,
            language:      $language,
            category:      $category,
            status:        TemplateStatus::fromProviderStatus($statusRaw),
            bodyText:      $bodyText,
            variableCount: $variableCount,
            providerMeta:  array_filter([
                'header'  => $tpl['structure']['header'] ?? null,
                'footer'  => $tpl['structure']['footer'] ?? null,
                'buttons' => $tpl['structure']['buttons'] ?? null,
            ]),
        );
    }
}

<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\ProviderTemplate;
use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\TemplateStatus;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsRegulatoryIds;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\SupportsTemplateFetch;
use WSms\Messaging\Contracts\SupportsTemplates;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

class Fast2SmsProvider extends AbstractProvider implements
    SupportsTemplates,
    SupportsTemplateFetch,
    SupportsStatusCallback,
    SupportsRegulatoryIds
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://www.fast2sms.com/dev';

    // TODO(verify): Fast2SMS exposes `route=otp` (POST /bulkV2 with route=otp +
    // variables_values) where the provider owns the message format
    // ("Your OTP: {value}"). That path is closer to a Verify-as-a-Service API
    // since it owns the lifecycle and rendering. Defer until SupportsVerify
    // lands; until then, OTPs flow through SupportsTemplates → DLT route via
    // TemplateCatalogManager.

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'fast2sms';
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
                    'description' => __('Your Fast2SMS API key from Dashboard > Dev API.', 'wp-sms'),
                ],
                'callback_token' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Shared secret appended to the DLR webhook URL as ?token= for verification. Fast2SMS does not sign callbacks; without this token the plugin rejects DLR posts.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('DLT Sender ID', 'wp-sms'),
                        'required'    => false,
                        'placeholder' => 'MYORG1',
                        'description' => __('6-character DLT-approved header. Required for the DLT route; ignored by the Quick route.', 'wp-sms'),
                    ],
                    'entity_id' => [
                        'type'        => 'string',
                        'label'       => __('DLT Principal Entity ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Your DLT Principal Entity ID, required when sending via the DLT route.', 'wp-sms'),
                    ],
                ],
                'whatsapp' => [
                    'phone_number_id' => [
                        'type'        => 'string',
                        'label'       => __('Phone Number ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Fast2SMS-assigned WhatsApp phone number ID, found under Console > WhatsApp.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('Fast2SMS API key not configured', 'wp-sms'));
        }

        return match ($message->getChannel()) {
            'whatsapp' => $this->sendWhatsApp($apiKey, $message),
            default    => $this->sendSms($apiKey, $message),
        };
    }

    // --- SMS ---

    private function sendSms(string $apiKey, MessageInterface $message): DeliveryResult
    {
        $meta = $message->getMeta();
        $recipient = $this->stripIndianCountryCode($message->getRecipient());

        $body = [
            'numbers' => $recipient,
        ];

        $template = $this->resolveTemplatePayload($meta);
        $regulatory = $this->resolveSmsRegulatoryPayload($meta);

        if ($template !== null) {
            $body['route']            = 'dlt';
            $body['message']          = $template['template_id'];
            $body['variables_values'] = $template['variables_values'];
            $body = array_merge($body, $regulatory);

            $senderId = $this->getChannelConfig('sms', 'sender_id');
            if ($senderId) {
                $body['sender_id'] = $senderId;
            }
        } else {
            $body['route']   = 'q';
            $body['message'] = $message->getBody();
        }

        if (!empty($meta['flash'])) {
            $body['flash'] = '1';
        }

        return $this->postBulk($apiKey, $body);
    }

    private function postBulk(string $apiKey, array $body): DeliveryResult
    {
        $result = $this->httpPost(self::API_BASE . '/bulkV2', [
            'headers' => $this->authHeaders($apiKey),
            'body'    => $body,
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Fast2SMS API key', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if (!is_array($data) || empty($data['return'])) {
            return DeliveryResult::failed($this->extractErrorMessage($data, $result['code']));
        }

        return DeliveryResult::sent(
            providerId: isset($data['request_id']) ? (string) $data['request_id'] : null,
        );
    }

    // --- WhatsApp ---

    private function sendWhatsApp(string $apiKey, MessageInterface $message): DeliveryResult
    {
        $phoneNumberId = $this->getChannelConfig('whatsapp', 'phone_number_id');
        if (!$phoneNumberId) {
            return DeliveryResult::failed(__('Fast2SMS WhatsApp Phone Number ID not configured', 'wp-sms'));
        }

        $meta = $message->getMeta();
        $recipient = $message->getRecipient();
        $template = $this->resolveTemplatePayload($meta);

        if ($template !== null) {
            return $this->sendWhatsAppTemplate($apiKey, $phoneNumberId, $recipient, $template, $meta);
        }

        $mediaUrls = $meta['media_urls'] ?? [];
        if (!empty($mediaUrls)) {
            return $this->sendWhatsAppSession($apiKey, $phoneNumberId, $recipient, [
                'type'  => 'image',
                'image' => array_filter([
                    'link'    => $mediaUrls[0],
                    'caption' => $message->getBody() ?: null,
                ]),
            ]);
        }

        return $this->sendWhatsAppSession($apiKey, $phoneNumberId, $recipient, [
            'type' => 'text',
            'text' => ['body' => $message->getBody()],
        ]);
    }

    private function sendWhatsAppTemplate(
        string $apiKey,
        string $phoneNumberId,
        string $recipient,
        array $template,
        array $meta
    ): DeliveryResult {
        $query = [
            'authorization'   => $apiKey,
            'message_id'      => $template['template_id'],
            'phone_number_id' => $phoneNumberId,
            'numbers'         => $recipient,
        ];

        if ($template['variables_values'] !== '') {
            $query['variables_values'] = $template['variables_values'];
        }

        $mediaUrls = $meta['media_urls'] ?? [];
        if (!empty($mediaUrls)) {
            $query['media_url'] = (string) $mediaUrls[0];
        }

        $url = add_query_arg($query, self::API_BASE . '/whatsapp');
        $result = $this->httpGet($url);

        return $this->parseWhatsAppResponse($result);
    }

    private function sendWhatsAppSession(
        string $apiKey,
        string $phoneNumberId,
        string $recipient,
        array $payload
    ): DeliveryResult {
        $body = array_merge([
            'to'              => $recipient,
            'phone_number_id' => $phoneNumberId,
        ], $payload);

        $result = $this->httpPost(self::API_BASE . '/whatsapp-session', [
            'headers' => array_merge($this->authHeaders($apiKey), [
                'Content-Type' => 'application/json',
            ]),
            'body'    => wp_json_encode($body),
        ]);

        return $this->parseWhatsAppResponse($result);
    }

    private function parseWhatsAppResponse(array|DeliveryResult $result): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Fast2SMS API key', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if (!is_array($data) || empty($data['return'])) {
            return DeliveryResult::failed($this->extractErrorMessage($data, $result['code']));
        }

        return DeliveryResult::sent(
            providerId: isset($data['request_id']) ? (string) $data['request_id'] : null,
        );
    }

    // --- Credit / Test connection ---

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/wallet', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || empty($data['return']) || !isset($data['wallet'])) {
            return null;
        }

        return (string) $data['wallet'];
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/wallet', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Fast2SMS API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Fast2SMS');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (empty($data['return'])) {
            $message = is_array($data['message'] ?? null) ? ($data['message'][0] ?? '') : ($data['message'] ?? '');
            return TestConnectionResult::error($message ?: __('Fast2SMS rejected the request', 'wp-sms'));
        }

        $wallet = (string) ($data['wallet'] ?? 'N/A');

        return TestConnectionResult::ok(
            sprintf(__('Connected — Wallet: ₹%s', 'wp-sms'), $wallet),
            ['balance' => $wallet],
        );
    }

    // --- SupportsTemplates ---

    public function requiresTemplateForChannel(string $channel): bool
    {
        // SMS: optional — Quick route accepts freeform text.
        // WhatsApp: business-initiated messages require approved templates,
        // but session replies (24-hour window) don't, so don't force-fail here.
        return false;
    }

    public function getVariableStyle(): VariableStyle
    {
        return VariableStyle::Positional;
    }

    public function buildTemplatePayload(TemplateMapping $mapping, array $resolvedVariables): array
    {
        ksort($resolvedVariables, SORT_NATURAL);

        return [
            'template_id'      => (string) $mapping->providerTemplateId,
            'variables_values' => implode('|', array_map('strval', $resolvedVariables)),
        ];
    }

    // --- SupportsTemplateFetch ---

    /** @return ProviderTemplate[] */
    public function fetchTemplates(): array
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return [];
        }

        try {
            $data = $this->fetchJsonOrFail(
                add_query_arg(['type' => 'template'], self::API_BASE . '/dlt_manager'),
                ['headers' => $this->authHeaders($apiKey)],
            );
        } catch (\RuntimeException) {
            return [];
        }

        $rows = $data['data'] ?? $data['templates'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $templates = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $template = $this->parseDltTemplate($row);
            if ($template) {
                $templates[] = $template;
            }
        }

        return $templates;
    }

    private function parseDltTemplate(array $row): ?ProviderTemplate
    {
        $id = (string) ($row['template_id'] ?? $row['id'] ?? '');
        if ($id === '') {
            return null;
        }

        $body = (string) ($row['template_message'] ?? $row['message'] ?? $row['body'] ?? '');
        $variableCount = preg_match_all('/\{#var#\}|\{\{\d+\}\}/', $body);

        return new ProviderTemplate(
            id:            $id,
            name:          (string) ($row['template_name'] ?? $row['name'] ?? $id),
            language:      (string) ($row['language'] ?? 'en'),
            category:      (string) ($row['template_type'] ?? $row['category'] ?? 'transactional'),
            status:        TemplateStatus::fromProviderStatus((string) ($row['status'] ?? 'approved')),
            bodyText:      $body,
            variableCount: (int) $variableCount,
            providerMeta:  array_filter([
                'sender_id' => $row['sender_id'] ?? null,
                'dlt_te_id' => $row['dlt_te_id'] ?? null,
            ]),
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        $token = $this->getSharedConfig('callback_token');
        $args = $token ? ['token' => $token] : [];
        return RestRoute::url('callbacks/' . $this->getId() . '/status', $args);
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        $expected = (string) $this->getSharedConfig('callback_token', '');
        if ($expected === '') {
            // Fast2SMS does not sign callbacks. Without a token configured the
            // endpoint is unauthenticated; refuse to process rather than trust
            // arbitrary inbound requests.
            return false;
        }

        $provided = (string) ($request->get_param('token') ?? '');
        if ($provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $providerId = (string) ($request->get_param('request_id') ?? $request->get_param('message_id') ?? '');
        $rawStatus = strtoupper((string) ($request->get_param('status') ?? ''));

        if ($providerId === '' || $rawStatus === '') {
            return [];
        }

        $status = match ($rawStatus) {
            'DELIVERED', 'READ'                => 'delivered',
            'FAILED', 'UNDELIVERED', 'REJECTED' => 'failed',
            'SENT', 'SUBMITTED'                => 'sent',
            'PENDING', 'QUEUED'                => 'queued',
            default                            => strtolower($rawStatus),
        };

        return [new StatusUpdate(
            providerId:   $providerId,
            status:       $status,
            errorCode:    $request->get_param('error_code') ? (string) $request->get_param('error_code') : null,
            errorMessage: $status === 'failed' ? sprintf('Fast2SMS: %s', $rawStatus) : null,
            permanent:    $status === 'failed',
        )];
    }

    // --- SupportsRegulatoryIds ---

    public function getRegulatoryConfigSchema(): array
    {
        return [
            'principal_entity_id' => [
                'type'        => 'string',
                'label'       => __('DLT Principal Entity ID', 'wp-sms'),
                'required'    => false,
                'description' => __('TRAI/DLT entity ID assigned to your business. Required for DLT-route sends.', 'wp-sms'),
            ],
        ];
    }

    public function buildRegulatoryPayload(array $regulatoryMeta): array
    {
        $entityId = $regulatoryMeta['principal_entity_id'] ?? '';
        if ($entityId === '') {
            return [];
        }

        return ['entity_id' => (string) $entityId];
    }

    // --- Internal ---

    private function authHeaders(string $apiKey): array
    {
        return [
            'authorization' => $apiKey,
            'Accept'        => 'application/json',
        ];
    }

    private function resolveTemplatePayload(array $meta): ?array
    {
        // Direct template mode — flow builder picked a provider template.
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

        // Catalog-resolved (system OTP / well-known template type).
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

    private function resolveSmsRegulatoryPayload(array $meta): array
    {
        $regulatory = $meta['regulatory'] ?? [];
        if (!is_array($regulatory)) {
            $regulatory = [];
        }

        if (empty($regulatory['principal_entity_id'])) {
            $configEntityId = (string) $this->getChannelConfig('sms', 'entity_id', '');
            if ($configEntityId !== '') {
                $regulatory = array_merge(['principal_entity_id' => $configEntityId], $regulatory);
            }
        }

        return $this->buildRegulatoryPayload($regulatory);
    }

    private function stripIndianCountryCode(string $number): string
    {
        $clean = preg_replace('/[\s\-]/', '', $number);
        foreach (['+91', '0091', '91'] as $prefix) {
            if (str_starts_with($clean, $prefix) && strlen($clean) - strlen($prefix) === 10) {
                return substr($clean, strlen($prefix));
            }
        }
        return $clean;
    }

    private function extractErrorMessage(mixed $data, int $statusCode): string
    {
        if (is_array($data)) {
            if (isset($data['message'])) {
                if (is_array($data['message'])) {
                    return (string) ($data['message'][0] ?? sprintf('HTTP %d', $statusCode));
                }
                return (string) $data['message'];
            }
            if (isset($data['error'])) {
                return (string) $data['error'];
            }
        }
        return sprintf('HTTP %d', $statusCode);
    }
}

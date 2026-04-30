<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsRegulatoryIds;
use WSms\Messaging\Contracts\SupportsTemplates;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

// SMSGatewayHub markets WhatsApp Business, Voice SMS, RCS, and Email channels
// (smsgatewayhub.com/whatsapp, /voice-sms, /RCS-Messaging-..., /bulk-email),
// but no public API reference documents endpoints, body shape, or webhook
// signing for them. SMS is the only channel with a verifiable transport
// (/http-api + the v7 production class) — extend here only when
// authoritative docs surface.
class SmsGatewayHubProvider extends AbstractProvider implements
    SupportsTemplates,
    SupportsRegulatoryIds
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://www.smsgatewayhub.com/api/mt';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'smsgatewayhub';
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
                    'description' => __('Your SMSGatewayHub API key from the Developer / API section of the dashboard.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('DLT Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'WSMSAB',
                        'description' => __('6-character DLT-approved alphabetical header.', 'wp-sms'),
                    ],
                    'route' => [
                        'type'    => 'select',
                        'label'   => __('Channel', 'wp-sms'),
                        'default' => 'Trans',
                        'options' => [
                            ['value' => 'Trans', 'label' => __('Transactional / OTP', 'wp-sms')],
                            ['value' => 'Promo', 'label' => __('Promotional', 'wp-sms')],
                        ],
                        'description' => __('Must match the DLT classification of the registered template.', 'wp-sms'),
                    ],
                    'entity_id' => [
                        'type'        => 'string',
                        'label'       => __('DLT Principal Entity ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Your DLT Principal Entity ID (PEID), assigned by your DLT operator.', 'wp-sms'),
                    ],
                    'template_id' => [
                        'type'        => 'string',
                        'label'       => __('Default DLT Template ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Default DLT-registered Content Template ID. Per-message overrides via the template catalog.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('SMSGatewayHub API key not configured', 'wp-sms'));
        }

        $senderId = (string) $this->getChannelConfig('sms', 'sender_id', '');
        if ($senderId === '') {
            return DeliveryResult::failed(__('SMSGatewayHub Sender ID not configured', 'wp-sms'));
        }

        $body = $message->getBody();
        $meta = $message->getMeta();

        $template = $this->resolveTemplatePayload($meta);
        if ($template !== null && !empty($template['text'])) {
            $body = $template['text'];
        }

        $query = [
            'APIKey'   => $apiKey,
            'senderid' => $senderId,
            // v7 (development branch) uses string values 'Trans'/'Promo' for the
            // channel parameter — not the numeric '1'/'2' some third-party docs
            // suggest. Mirroring v7 since it ships in production.
            'channel'  => $this->resolveRouteValue($this->getChannelConfig('sms', 'route', 'Trans')),
            'DCS'      => $this->isUnicode($body) ? '8' : '0',
            'flashsms' => !empty($meta['flash']) ? '1' : '0',
            'number'   => $this->stripIndianCountryCode($message->getRecipient()),
            'text'     => $body,
            'route'    => '1',
        ];

        $regulatory = $this->resolveRegulatoryPayload($meta, $template);
        $query = array_merge($query, $regulatory);

        $url = add_query_arg($query, self::API_BASE . '/SendSMS');
        $result = $this->httpGet($url);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid SMSGatewayHub API key', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if (!is_array($data)) {
            return DeliveryResult::failed(sprintf('HTTP %d', $result['code']));
        }

        $errorCode = (string) ($data['ErrorCode'] ?? '');
        if ($errorCode !== '000') {
            $errorMessage = (string) ($data['ErrorMessage'] ?? sprintf(__('SMSGatewayHub error %s', 'wp-sms'), $errorCode));
            return DeliveryResult::failed($errorMessage, array_filter([
                'smsgatewayhub_error_code' => $errorCode !== '' ? $errorCode : null,
                'smsgatewayhub_job_id'     => $data['JobId'] ?? null,
            ]));
        }

        $messageId = $data['MessageData'][0]['MessageId'] ?? $data['JobId'] ?? null;

        return DeliveryResult::sent(
            providerId: $messageId !== null ? (string) $messageId : null,
            meta: array_filter(['smsgatewayhub_job_id' => $data['JobId'] ?? null]),
        );
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpGet(add_query_arg(
            ['APIKey' => $apiKey],
            self::API_BASE . '/GetBalance',
        ));

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['Balance'])) {
            return null;
        }

        return (string) $data['Balance'];
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(add_query_arg(
            ['APIKey' => $apiKey],
            self::API_BASE . '/GetBalance',
        ));

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid SMSGatewayHub API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'SMSGatewayHub');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (!isset($data['Balance'])) {
            $errorMessage = (string) ($data['ErrorMessage'] ?? __('SMSGatewayHub rejected the request', 'wp-sms'));
            return TestConnectionResult::error($errorMessage);
        }

        $balance = (string) $data['Balance'];

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s', 'wp-sms'), $balance),
            ['balance' => $balance],
        );
    }

    // --- SupportsTemplates ---

    public function requiresTemplateForChannel(string $channel): bool
    {
        // DLT template registration is required by Indian carriers, but the
        // SendSMS endpoint accepts freeform text — let the platform fall back
        // to plain text when no template mapping is configured.
        return false;
    }

    public function getVariableStyle(): VariableStyle
    {
        return VariableStyle::Positional;
    }

    public function buildTemplatePayload(TemplateMapping $mapping, array $resolvedVariables): array
    {
        ksort($resolvedVariables, SORT_NATURAL);

        $text = $mapping->providerTemplateBody !== ''
            ? $this->renderTemplateBody($mapping->providerTemplateBody, $resolvedVariables)
            : (string) reset($resolvedVariables);

        return [
            'template_id' => (string) $mapping->providerTemplateId,
            'text'        => $text,
        ];
    }

    // --- SupportsRegulatoryIds ---

    public function getRegulatoryConfigSchema(): array
    {
        return [
            'principal_entity_id' => [
                'type'        => 'string',
                'label'       => __('DLT Principal Entity ID', 'wp-sms'),
                'required'    => false,
                'description' => __('TRAI/DLT entity ID assigned to your business.', 'wp-sms'),
            ],
            'content_template_id' => [
                'type'        => 'string',
                'label'       => __('DLT Content Template ID', 'wp-sms'),
                'required'    => false,
                'description' => __('DLT-registered Content Template ID for this message.', 'wp-sms'),
            ],
        ];
    }

    public function buildRegulatoryPayload(array $regulatoryMeta): array
    {
        $payload = [];
        $entityId = (string) ($regulatoryMeta['principal_entity_id'] ?? '');
        $templateId = (string) ($regulatoryMeta['content_template_id'] ?? '');

        if ($entityId !== '') {
            $payload['PEID'] = $entityId;
        }
        if ($templateId !== '') {
            $payload['DLTTemplateId'] = $templateId;
        }

        return $payload;
    }

    // --- Internal ---

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
                providerTemplateBody: (string) ($meta['template_body'] ?? ''),
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

    private function resolveRegulatoryPayload(array $meta, ?array $template): array
    {
        $regulatory = $meta['regulatory'] ?? [];
        if (!is_array($regulatory)) {
            $regulatory = [];
        }

        if (empty($regulatory['principal_entity_id'])) {
            $configEntity = (string) $this->getChannelConfig('sms', 'entity_id', '');
            if ($configEntity !== '') {
                $regulatory['principal_entity_id'] = $configEntity;
            }
        }

        if (empty($regulatory['content_template_id'])) {
            if ($template !== null && !empty($template['template_id'])) {
                $regulatory['content_template_id'] = (string) $template['template_id'];
            } else {
                $configTemplate = (string) $this->getChannelConfig('sms', 'template_id', '');
                if ($configTemplate !== '') {
                    $regulatory['content_template_id'] = $configTemplate;
                }
            }
        }

        return $this->buildRegulatoryPayload($regulatory);
    }

    private function resolveRouteValue(string $route): string
    {
        return $route === 'Promo' ? 'Promo' : 'Trans';
    }

    private function isUnicode(string $text): bool
    {
        return $text !== '' && !mb_check_encoding($text, 'ASCII');
    }

    private function stripIndianCountryCode(string $number): string
    {
        $clean = preg_replace('/[\s\-]/', '', $number);
        foreach (['+91', '0091', '91'] as $prefix) {
            if (str_starts_with($clean, $prefix) && strlen($clean) - strlen($prefix) === 10) {
                return substr($clean, strlen($prefix));
            }
        }
        return ltrim($clean, '+');
    }

    private function renderTemplateBody(string $body, array $resolvedVariables): string
    {
        $rendered = $body;
        $i = 0;
        $rendered = preg_replace_callback('/\{#var#\}/', function () use ($resolvedVariables, &$i) {
            $i++;
            return (string) ($resolvedVariables[(string) $i] ?? '');
        }, $rendered);

        foreach ($resolvedVariables as $key => $value) {
            $rendered = str_replace('{{' . $key . '}}', (string) $value, $rendered);
        }

        return $rendered;
    }
}

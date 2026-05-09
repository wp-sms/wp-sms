<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\ProviderTemplate;
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
use WSms\Messaging\Contracts\SupportsTemplates;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

/**
 * Gupshup — multi-channel.
 *
 * SMS and RCS share the Enterprise API endpoint (enterprise.smsgupshup.com,
 * userid+password form auth) — RCS just JSON-encodes its rich payload into the
 * `msg` field. WhatsApp uses the Conversational Messaging API (api.gupshup.io,
 * apikey header, form-encoded with JSON message field).
 *
 * Webhooks across all channels are unsigned — the provider exposes a per-channel
 * URL token and rejects callbacks where the ?token= query parameter doesn't match.
 */
class GupshupProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsOptOutDetection,
    SupportsTemplates,
    SupportsTemplateFetch,
    SupportsRegulatoryIds
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SMS_API  = 'https://enterprise.smsgupshup.com/GatewayAPI/rest';
    private const WA_API   = 'https://api.gupshup.io/wa/api/v1/msg';
    private const WA_PARTNER_BASE = 'https://api.gupshup.io/wa/app';

    // TODO(verify): Gupshup 2FA product exists at /sm/api/v1/otp; defer until SupportsVerify lands.

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'gupshup';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp', 'rcs'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared'   => [],
            'channels' => [
                'sms' => [
                    'userid' => [
                        'type'        => 'string',
                        'label'       => __('User ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Numeric account ID from your Gupshup Enterprise SMS account.', 'wp-sms'),
                    ],
                    'password' => [
                        'type'        => 'secret',
                        'label'       => __('Password', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Account password used for the SMS API.', 'wp-sms'),
                    ],
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Approved alphanumeric sender mask (e.g., WSMSAB).', 'wp-sms'),
                        'placeholder' => 'WSMSAB',
                    ],
                    'webhook_token' => [
                        'type'        => 'secret',
                        'label'       => __('Webhook URL Token', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Random secret appended to your callback URL as ?token=… — Gupshup does not sign webhooks.', 'wp-sms'),
                    ],
                ],
                'whatsapp' => [
                    'api_key' => [
                        'type'        => 'secret',
                        'label'       => __('API Key', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Gupshup API key for the Conversational Messaging Platform (apikey header).', 'wp-sms'),
                    ],
                    'app_name' => [
                        'type'        => 'string',
                        'label'       => __('App Name (src.name)', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Name of your WhatsApp app in Gupshup, used as src.name.', 'wp-sms'),
                    ],
                    'source_number' => [
                        'type'        => 'string',
                        'label'       => __('Source Number (E.164)', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Your WhatsApp business number in E.164 format.', 'wp-sms'),
                        'placeholder' => '+15551234567',
                    ],
                    'webhook_token' => [
                        'type'        => 'secret',
                        'label'       => __('Webhook URL Token', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Random secret appended to your callback URL as ?token=… — Gupshup does not sign webhooks.', 'wp-sms'),
                    ],
                ],
                'rcs' => [
                    'userid' => [
                        'type'        => 'string',
                        'label'       => __('User ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Gupshup Enterprise account ID with RCS enabled (often the same as your SMS User ID).', 'wp-sms'),
                    ],
                    'password' => [
                        'type'        => 'secret',
                        'label'       => __('Password', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Account password used for the Enterprise RCS API.', 'wp-sms'),
                    ],
                    'agent_id' => [
                        'type'        => 'string',
                        'label'       => __('RCS Agent ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Approved RCS Agent identifier (informational; routing is bound to your account).', 'wp-sms'),
                    ],
                    'webhook_token' => [
                        'type'        => 'secret',
                        'label'       => __('Webhook URL Token', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Random secret appended to your callback URL as ?token=… — Gupshup does not sign webhooks.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        return match ($message->getChannel()) {
            'whatsapp' => $this->sendWhatsApp($message),
            'rcs'      => $this->sendRcs($message),
            default    => $this->sendSms($message),
        };
    }

    // --- SMS ---

    private function sendSms(MessageInterface $message): DeliveryResult
    {
        $userid   = $this->getChannelConfig('sms', 'userid');
        $password = $this->getChannelConfig('sms', 'password');
        $sender   = $this->getChannelConfig('sms', 'sender_id');

        if (!$userid || !$password || !$sender) {
            return DeliveryResult::failed(__('Gupshup SMS credentials not configured', 'wp-sms'));
        }

        $meta = $message->getMeta();

        $body = [
            'method'      => 'SendMessage',
            'userid'      => $userid,
            'password'    => $password,
            'auth_scheme' => 'plain',
            'v'           => '1.1',
            'format'      => 'json',
            'send_to'     => $message->getRecipient(),
            'msg'         => $message->getBody(),
            'msg_type'    => !empty($meta['unicode']) ? 'unicode_text' : 'text',
            'mask'        => $sender,
        ];

        if (!empty($meta['flash'])) {
            $body['msg_type'] = 'flash';
        }

        $regulatory = $this->buildRegulatoryPayload($meta['regulatory'] ?? []);
        if (!empty($regulatory)) {
            $body = array_merge($body, $regulatory);
        }

        $result = $this->httpPost(self::SMS_API, ['body' => $body]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Gupshup SMS credentials', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);
        $response = is_array($data) ? ($data['response'] ?? []) : [];
        $status = strtolower((string) ($response['status'] ?? ''));

        if ($status === 'success' && !empty($response['id'])) {
            return DeliveryResult::sent((string) $response['id']);
        }

        return DeliveryResult::failed(
            (string) ($response['details'] ?? $response['phone'] ?? "HTTP {$result['code']}"),
            meta: array_filter([
                'gupshup_error_code' => $response['code'] ?? null,
            ]),
        );
    }

    // --- WhatsApp ---

    private function sendWhatsApp(MessageInterface $message): DeliveryResult
    {
        $apiKey  = $this->getChannelConfig('whatsapp', 'api_key');
        $appName = $this->getChannelConfig('whatsapp', 'app_name');
        $source  = $this->getChannelConfig('whatsapp', 'source_number');

        if (!$apiKey || !$appName || !$source) {
            return DeliveryResult::failed(__('Gupshup WhatsApp credentials not configured', 'wp-sms'));
        }

        $meta = $message->getMeta();
        $templatePayload = $this->resolveTemplatePayload($meta);

        $body = [
            'channel'     => 'whatsapp',
            'source'      => $source,
            'destination' => $message->getRecipient(),
            'src.name'    => $appName,
        ];

        if ($templatePayload !== null) {
            $body['template'] = wp_json_encode($templatePayload);
            $body['message']  = wp_json_encode(['type' => 'text', 'text' => $message->getBody()]);
        } else {
            $body['message'] = wp_json_encode($this->buildWhatsAppMessage($message, $meta));
        }

        $result = $this->httpPost(self::WA_API, [
            'headers' => [
                'apikey' => $apiKey,
            ],
            'body' => $body,
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Gupshup WhatsApp API key', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] >= 200 && $result['code'] < 300 && !empty($data['messageId'])) {
            return DeliveryResult::queued((string) $data['messageId']);
        }

        return DeliveryResult::failed(
            (string) ($data['message'] ?? $data['error'] ?? "HTTP {$result['code']}"),
            meta: array_filter([
                'gupshup_error_code' => isset($data['code']) ? (string) $data['code'] : null,
            ]),
        );
    }

    private function buildWhatsAppMessage(MessageInterface $message, array $meta): array
    {
        $mediaUrls = $meta['media_urls'] ?? [];
        if (!empty($mediaUrls)) {
            return array_filter([
                'type'        => 'image',
                'originalUrl' => $mediaUrls[0],
                'previewUrl'  => $mediaUrls[0],
                'caption'     => $message->getBody() ?: null,
            ]);
        }

        return [
            'type' => 'text',
            'text' => $message->getBody(),
        ];
    }

    // --- RCS ---

    private function sendRcs(MessageInterface $message): DeliveryResult
    {
        $userid   = $this->getChannelConfig('rcs', 'userid');
        $password = $this->getChannelConfig('rcs', 'password');

        if (!$userid || !$password) {
            return DeliveryResult::failed(__('Gupshup RCS credentials not configured', 'wp-sms'));
        }

        $body = [
            'method'      => 'SendMessage',
            'userid'      => $userid,
            'password'    => $password,
            'auth_scheme' => 'plain',
            'v'           => '1.1',
            'format'      => 'json',
            'send_to'     => $message->getRecipient(),
            'msg_type'    => 'TEXT',
            'msg'         => wp_json_encode($this->buildRcsMessage($message)),
        ];

        $result = $this->httpPost(self::SMS_API, ['body' => $body]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Gupshup RCS credentials', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);
        $response = is_array($data) ? ($data['response'] ?? []) : [];
        $status = strtolower((string) ($response['status'] ?? ''));

        if ($status === 'success' && !empty($response['id'])) {
            return DeliveryResult::sent((string) $response['id']);
        }

        return DeliveryResult::failed(
            (string) ($response['details'] ?? "HTTP {$result['code']}"),
            meta: array_filter([
                'gupshup_error_code' => $response['code'] ?? null,
            ]),
        );
    }

    private function buildRcsMessage(MessageInterface $message): array
    {
        $meta = $message->getMeta();
        $mediaUrls = $meta['media_urls'] ?? [];

        if (!empty($mediaUrls)) {
            return array_filter([
                'type' => 'image',
                'url'  => $mediaUrls[0],
                'text' => $message->getBody() ?: null,
            ]);
        }

        return [
            'type' => 'text',
            'text' => $message->getBody(),
        ];
    }

    // --- Credit / Test connection ---

    public function getCredit(): ?string
    {
        $userid   = $this->getChannelConfig('sms', 'userid');
        $password = $this->getChannelConfig('sms', 'password');

        if (!$userid || !$password) {
            return null;
        }

        // WhatsApp partner-API balance lives behind a separate Partner token, not the
        // app apikey, so we only expose the SMS wallet here.
        $url = add_query_arg([
            'method'      => 'CHECK_BALANCE',
            'userid'      => $userid,
            'password'    => $password,
            'auth_scheme' => 'plain',
            'format'      => 'json',
            'v'           => '1.1',
        ], self::SMS_API);

        $result = $this->httpGet($url);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        $balance = $data['response']['balance'] ?? null;
        if ($balance === null) {
            return null;
        }

        return (string) $balance;
    }

    public function testConnection(): TestConnectionResult
    {
        $userid   = $this->getChannelConfig('sms', 'userid');
        $password = $this->getChannelConfig('sms', 'password');

        if ($userid && $password) {
            return $this->testSmsApi($userid, $password);
        }

        $apiKey  = $this->getChannelConfig('whatsapp', 'api_key');
        $appName = $this->getChannelConfig('whatsapp', 'app_name');
        if ($apiKey && $appName) {
            return $this->testWhatsAppApi($apiKey, $appName);
        }

        return TestConnectionResult::error(__('Configure at least one channel before testing.', 'wp-sms'));
    }

    private function testSmsApi(string $userid, string $password): TestConnectionResult
    {
        $url = add_query_arg([
            'method'      => 'CHECK_BALANCE',
            'userid'      => $userid,
            'password'    => $password,
            'auth_scheme' => 'plain',
            'format'      => 'json',
            'v'           => '1.1',
        ], self::SMS_API);

        $result = $this->httpGet($url);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Gupshup SMS credentials', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Gupshup');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = $data['response']['balance'] ?? null;
        if ($balance === null) {
            return TestConnectionResult::error(__('Gupshup rejected the credentials', 'wp-sms'));
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s', 'wp-sms'), $balance),
            ['balance' => (string) $balance],
        );
    }

    private function testWhatsAppApi(string $apiKey, string $appName): TestConnectionResult
    {
        $url = self::WA_PARTNER_BASE . '/' . rawurlencode($appName) . '/template';

        $result = $this->httpGet($url, [
            'headers' => ['apikey' => $apiKey],
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Gupshup WhatsApp API key', 'wp-sms'));
            }
            if ($result['code'] === 404) {
                return TestConnectionResult::error(__('App not found — check your App Name', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Gupshup WhatsApp');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        return TestConnectionResult::ok(__('Connected to Gupshup WhatsApp', 'wp-sms'));
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        $token = $this->preferredWebhookToken();
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
        $jsonBody = $request->get_body();
        $jsonParams = $jsonBody ? (json_decode($jsonBody, true) ?: []) : [];

        if (isset($jsonParams['type']) && $jsonParams['type'] === 'message-event') {
            return $this->parseWhatsAppStatusEvent($jsonParams['payload'] ?? []);
        }

        $params = $request->get_params();
        if (!empty($params['externalId']) && isset($params['status'])) {
            return $this->parseSmsDeliveryReport($params);
        }

        return [];
    }

    /** @return StatusUpdate[] */
    private function parseWhatsAppStatusEvent(array $payload): array
    {
        $providerId = (string) ($payload['id'] ?? '');
        $type = strtolower((string) ($payload['type'] ?? ''));
        if ($providerId === '' || $type === '') {
            return [];
        }

        $status = match ($type) {
            'enqueued', 'queued' => 'queued',
            'sent'               => 'sent',
            'delivered', 'read'  => 'delivered',
            'failed'             => 'failed',
            default              => $type,
        };

        $code = isset($payload['payload']['code']) ? (string) $payload['payload']['code'] : null;
        $reason = $payload['payload']['reason'] ?? null;

        return [new StatusUpdate(
            providerId:   $providerId,
            status:       $status,
            errorCode:    $code,
            errorMessage: $status === 'failed' && $reason ? sprintf('Gupshup: %s', $reason) : null,
            permanent:    $status === 'failed',
        )];
    }

    /** @return StatusUpdate[] */
    private function parseSmsDeliveryReport(array $params): array
    {
        $providerId = (string) ($params['externalId'] ?? '');
        $rawStatus = strtoupper((string) ($params['status'] ?? ''));
        if ($providerId === '' || $rawStatus === '') {
            return [];
        }

        $status = match ($rawStatus) {
            'SUCCESS', 'DELIVERED'             => 'delivered',
            'ENROUTE', 'SUBMITTED'             => 'sent',
            'PENDING'                          => 'queued',
            default                            => 'failed',
        };

        $permanent = $status === 'failed' && in_array($rawStatus, [
            'FAIL', 'FAILED', 'UNKNOWN_SUBSCRIBER', 'BLOCKED', 'REJECTED', 'EXPIRED', 'ABSENT_SUBSCRIBER',
        ], true);

        return [new StatusUpdate(
            providerId:   $providerId,
            status:       $status,
            errorCode:    $rawStatus !== 'SUCCESS' ? $rawStatus : null,
            errorMessage: $status === 'failed' ? sprintf('Gupshup: %s', $rawStatus) : null,
            permanent:    $permanent,
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        $token = $this->preferredWebhookToken();
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
        $jsonBody = $request->get_body();
        $jsonParams = $jsonBody ? (json_decode($jsonBody, true) ?: []) : [];

        if (isset($jsonParams['type']) && $jsonParams['type'] === 'message') {
            return $this->parseWhatsAppInbound($jsonParams['payload'] ?? []);
        }

        $params = $request->get_params();
        if (!empty($params['phoneNo']) && isset($params['message'])) {
            return $this->parseSmsInbound($params);
        }

        return [];
    }

    /** @return InboundMessage[] */
    private function parseWhatsAppInbound(array $payload): array
    {
        $from = (string) ($payload['source'] ?? '');
        if ($from === '') {
            return [];
        }

        $type = (string) ($payload['type'] ?? 'text');
        $inner = $payload['payload'] ?? [];
        $body = (string) ($inner['text'] ?? $inner['caption'] ?? '');

        $mediaUrls = [];
        if (!empty($inner['url']) && in_array($type, ['image', 'video', 'audio', 'file'], true)) {
            $mediaUrls[] = $inner['url'];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($payload['destination'] ?? ''),
            body:       $body,
            providerId: isset($payload['id']) ? (string) $payload['id'] : null,
            meta:       array_filter([
                'type'       => $type,
                'media_urls' => $mediaUrls ?: null,
            ]),
        )];
    }

    /** @return InboundMessage[] */
    private function parseSmsInbound(array $params): array
    {
        $from = (string) ($params['phoneNo'] ?? '');
        if ($from === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($params['sender'] ?? ''),
            body:       (string) ($params['message'] ?? ''),
            providerId: isset($params['msgId']) ? (string) $params['msgId'] : null,
        )];
    }

    // --- SupportsTemplates ---

    public function requiresTemplateForChannel(string $channel): bool
    {
        // WhatsApp requires Meta-approved templates outside the 24-hour customer-service
        // window. DLT enforcement on the SMS side is operator-side; RCS doesn't require
        // pre-approved templates either — admins can still send freeform messages.
        return $channel === 'whatsapp';
    }

    public function getVariableStyle(): VariableStyle
    {
        return VariableStyle::Positional;
    }

    public function buildTemplatePayload(TemplateMapping $mapping, array $resolvedVariables): array
    {
        ksort($resolvedVariables, SORT_NATURAL);

        return [
            'id'     => (string) $mapping->providerTemplateId,
            'params' => array_values(array_map('strval', $resolvedVariables)),
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

    // --- SupportsTemplateFetch ---

    /** @return ProviderTemplate[] */
    public function fetchTemplates(): array
    {
        $apiKey  = $this->getChannelConfig('whatsapp', 'api_key');
        $appName = $this->getChannelConfig('whatsapp', 'app_name');
        if (!$apiKey || !$appName) {
            return [];
        }

        try {
            $data = $this->fetchJsonOrFail(
                self::WA_PARTNER_BASE . '/' . rawurlencode($appName) . '/template',
                ['headers' => ['apikey' => $apiKey]],
            );
        } catch (\RuntimeException) {
            return [];
        }

        $rows = $data['templates'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $templates = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $template = $this->parseWhatsAppTemplate($row);
            if ($template) {
                $templates[] = $template;
            }
        }

        return $templates;
    }

    private function parseWhatsAppTemplate(array $row): ?ProviderTemplate
    {
        $id = (string) ($row['id'] ?? '');
        if ($id === '') {
            return null;
        }

        $body = (string) ($row['data'] ?? $row['body'] ?? '');
        $variableCount = preg_match_all('/\{\{\d+\}\}/', $body);

        return new ProviderTemplate(
            id:            $id,
            name:          (string) ($row['elementName'] ?? $row['name'] ?? $id),
            language:      (string) ($row['languageCode'] ?? $row['language'] ?? 'en'),
            category:      strtolower((string) ($row['category'] ?? 'utility')),
            status:        TemplateStatus::fromProviderStatus((string) ($row['status'] ?? 'pending')),
            bodyText:      $body,
            variableCount: (int) $variableCount,
        );
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
            'dlt_template_id' => [
                'type'        => 'string',
                'label'       => __('DLT Template ID', 'wp-sms'),
                'required'    => false,
                'description' => __('DLT-registered Content Template ID for this message.', 'wp-sms'),
            ],
        ];
    }

    public function buildRegulatoryPayload(array $regulatoryMeta): array
    {
        $payload = [];
        $entityId = (string) ($regulatoryMeta['principal_entity_id'] ?? '');
        $templateId = (string) ($regulatoryMeta['dlt_template_id'] ?? '');

        if ($entityId !== '') {
            $payload['principalEntityId'] = $entityId;
        }
        if ($templateId !== '') {
            $payload['dltTemplateId'] = $templateId;
        }

        return $payload;
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        $code = $result->meta['gupshup_error_code'] ?? null;
        if ($code === null) {
            return false;
        }

        return in_array((string) $code, [
            '1008',                 // SMS: opted out
            'UNKNOWN_SUBSCRIBER',   // SMS DLR: number not on network
            'BLOCKED',              // SMS DLR: blocked recipient
            '471',                  // WhatsApp: recipient blocked the business
        ], true);
    }

    // --- Internal ---

    private function preferredWebhookToken(): string
    {
        // All channels share one callback URL per app; surface whichever token is
        // configured. SMS wins when multiple exist (most common production path),
        // then RCS, then WhatsApp.
        foreach (['sms', 'rcs', 'whatsapp'] as $channel) {
            $token = (string) $this->getChannelConfig($channel, 'webhook_token', '');
            if ($token !== '') {
                return $token;
            }
        }
        return '';
    }

    private function verifyWebhookToken(\WP_REST_Request $request): bool
    {
        $provided = (string) ($request->get_param('token') ?? '');
        if ($provided === '') {
            return false;
        }

        foreach (['sms', 'whatsapp', 'rcs'] as $channel) {
            $expected = (string) $this->getChannelConfig($channel, 'webhook_token', '');
            if ($expected !== '' && hash_equals($expected, $provided)) {
                return true;
            }
        }
        return false;
    }
}

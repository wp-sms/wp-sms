<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsTemplates;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

class TubelightCommunicationsProvider extends AbstractProvider implements SupportsTemplates
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://portal.tubelightcommunications.com';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'tubelightcommunications';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'username' => [
                    'type'        => 'string',
                    'label'       => __('Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Tubelight Communications portal username.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Tubelight Communications portal password.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('DLT Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'MYORG1',
                        'description' => __('6-character DLT-approved sender header registered with your operator.', 'wp-sms'),
                    ],
                ],
                'whatsapp' => [],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $token = $this->accessToken();
        if ($token instanceof DeliveryResult) {
            return $token;
        }

        return match ($message->getChannel()) {
            'whatsapp' => $this->sendWhatsApp($token, $message),
            default    => $this->sendSms($token, $message),
        };
    }

    // --- SMS ---

    private function sendSms(string $token, MessageInterface $message): DeliveryResult
    {
        $senderId = $this->getChannelConfig('sms', 'sender_id');
        if (!$senderId) {
            return DeliveryResult::failed(__('Tubelight Communications DLT Sender ID not configured', 'wp-sms'));
        }

        $meta = $message->getMeta();
        $template = $this->resolveTemplatePayload($meta);
        $tempId = $template !== null ? (string) $template['template_id'] : '';

        $body = [[
            'sender'      => (string) $senderId,
            'mobileNo'    => $message->getRecipient(),
            'messageType' => 'TEXT',
            'messages'    => $message->getBody(),
            'tempId'      => $tempId,
        ]];

        $result = $this->httpPost(self::API_BASE . '/sms/api/v1/websms/bulksend', [
            'headers' => $this->authHeaders($token),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Tubelight Communications credentials', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed($this->extractErrorMessage($data, $result['code']));
        }

        return DeliveryResult::sent(
            providerId: $this->extractSmsProviderId($data),
        );
    }

    // --- WhatsApp ---

    private function sendWhatsApp(string $token, MessageInterface $message): DeliveryResult
    {
        $meta = $message->getMeta();
        $template = $this->resolveTemplatePayload($meta);
        $mediaUrls = $meta['media_urls'] ?? [];

        if ($template === null) {
            return DeliveryResult::failed(__('Tubelight Communications WhatsApp requires an approved template', 'wp-sms'));
        }

        $body = [
            'to'      => [$message->getRecipient()],
            'message' => [
                'template_name' => (string) $template['template_id'],
                'type'          => 'template',
                'body_params'   => $template['variables'],
                'header_params' => array_values($mediaUrls),
            ],
        ];

        $result = $this->httpPost(self::API_BASE . '/whatsapp/api/v1/send', [
            'headers' => $this->authHeaders($token),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Tubelight Communications credentials', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed($this->extractErrorMessage($data, $result['code']));
        }

        return DeliveryResult::sent(
            providerId: $this->extractWhatsAppProviderId($data),
        );
    }

    // --- Credit / Test Connection ---

    public function getCredit(): ?string
    {
        $token = $this->accessToken();
        if ($token instanceof DeliveryResult) {
            return null;
        }

        $result = $this->httpPost(self::API_BASE . '/sms/api/v1/balance', [
            'headers' => $this->authHeaders($token),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['balance'])) {
            return null;
        }

        return (string) $data['balance'];
    }

    public function testConnection(): TestConnectionResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return TestConnectionResult::error(__('Username and Password are required', 'wp-sms'));
        }

        $token = $this->accessToken();
        if ($token instanceof DeliveryResult) {
            return TestConnectionResult::error(__('Invalid Tubelight Communications credentials', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/sms/api/v1/balance', [
            'headers' => $this->authHeaders($token),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Tubelight Communications credentials', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Tubelight Communications');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = (string) ($data['balance'] ?? 'N/A');

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s', 'wp-sms'), $balance),
            ['balance' => $balance],
        );
    }

    // --- SupportsTemplates ---

    public function requiresTemplateForChannel(string $channel): bool
    {
        // SMS may run without DLT in test routes; WhatsApp 24h session window
        // can run text — so don't force-fail at this layer.
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
            'template_id' => (string) $mapping->providerTemplateId,
            'variables'   => array_values(array_map('strval', $resolvedVariables)),
        ];
    }

    // --- Internal ---

    private function accessToken(): string|DeliveryResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return DeliveryResult::failed(__('Tubelight Communications credentials not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/api/authentication/login', [
            'headers' => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'username' => (string) $username,
                'password' => (string) $password,
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Tubelight Communications credentials', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);
        $token = is_array($data) ? ($data['accessToken'] ?? null) : null;

        if (!is_string($token) || $token === '') {
            return DeliveryResult::failed(__('Tubelight Communications authorization error', 'wp-sms'));
        }

        return $token;
    }

    private function authHeaders(string $token): array
    {
        return [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $token,
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

    private function extractSmsProviderId(mixed $data): ?string
    {
        if (!is_array($data)) {
            return null;
        }
        foreach (['refId', 'ref_id', 'requestId', 'request_id', 'id', 'messageId'] as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                return (string) $data[$key];
            }
        }
        if (isset($data[0]) && is_array($data[0])) {
            return $this->extractSmsProviderId($data[0]);
        }
        return null;
    }

    private function extractWhatsAppProviderId(mixed $data): ?string
    {
        if (!is_array($data)) {
            return null;
        }
        foreach (['messageId', 'message_id', 'id', 'refId'] as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                return (string) $data[$key];
            }
        }
        return null;
    }

    private function extractErrorMessage(mixed $data, int $statusCode): string
    {
        if (is_array($data)) {
            foreach (['message', 'error', 'error_description', 'description'] as $key) {
                if (isset($data[$key])) {
                    if (is_array($data[$key])) {
                        return (string) ($data[$key][0] ?? sprintf('HTTP %d', $statusCode));
                    }
                    return (string) $data[$key];
                }
            }
        }
        return sprintf('HTTP %d', $statusCode);
    }
}

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
 * MessageBird / Bird Channels API provider.
 *
 * Uses Bird's current Channels API at api.bird.com, not the legacy
 * rest.messagebird.com SMS SDK. Verify and Voice are intentionally deferred
 * because WSMS currently has no Verify or Voice channel contracts.
 */
class MessageBirdProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsOptOutDetection,
    SupportsTemplates
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.bird.com';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'messagebird';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp', 'rcs'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'access_key' => [
                    'type'        => 'secret',
                    'label'       => __('Access Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Bird API access key used as Authorization: AccessKey ...', 'wp-sms'),
                ],
                'workspace_id' => [
                    'type'        => 'string',
                    'label'       => __('Workspace ID', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Workspace UUID from the Bird dashboard.', 'wp-sms'),
                    'placeholder' => '123e4567-e89b-12d3-a456-426614174000',
                ],
                'organization_id' => [
                    'type'        => 'string',
                    'label'       => __('Organization ID', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Optional Bird organization ID for admin reference.', 'wp-sms'),
                ],
                'webhook_signing_key' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Signing Key', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Signing key configured on Bird webhook subscriptions. Required for delivery reports and inbound messages.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'channel_id' => [
                        'type'        => 'string',
                        'label'       => __('SMS Channel ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Bird SMS channel UUID.', 'wp-sms'),
                    ],
                ],
                'whatsapp' => [
                    'channel_id' => [
                        'type'        => 'string',
                        'label'       => __('WhatsApp Channel ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Bird WhatsApp channel UUID.', 'wp-sms'),
                    ],
                ],
                'rcs' => [
                    'channel_id' => [
                        'type'        => 'string',
                        'label'       => __('RCS Channel ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Bird RCS channel UUID.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'mms'              => true,
            'flash_sms'        => false,
            'delivery_receipt' => true,
            'incoming'         => true,
            'unicode'          => true,
            'media'            => true,
            'test_connection'  => true,
        ]);
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $accessKey = $this->getSharedConfig('access_key');
        $workspaceId = $this->getSharedConfig('workspace_id');
        $channel = $message->getChannel();
        $channelId = $this->getChannelConfig($channel, 'channel_id');

        if (!$accessKey || !$workspaceId) {
            return DeliveryResult::failed(__('MessageBird Access Key and Workspace ID are required', 'wp-sms'));
        }

        if (!in_array($channel, $this->getSupportedChannels(), true)) {
            return DeliveryResult::failed(sprintf(__('MessageBird does not support channel %s', 'wp-sms'), $channel));
        }

        if (!$channelId) {
            return DeliveryResult::failed(sprintf(__('MessageBird %s Channel ID is not configured', 'wp-sms'), strtoupper($channel)));
        }

        $payload = $this->buildSendPayload($message);
        $result = $this->httpPost($this->messagesUrl((string) $workspaceId, (string) $channelId), [
            'headers' => $this->authHeaders((string) $accessKey),
            'body'    => wp_json_encode($payload),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $decoded = json_decode($result['body'], true);
        $data = is_array($decoded) ? $decoded : [];

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid MessageBird Access Key', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300) {
            $status = $this->normalizeStatus((string) ($data['status'] ?? 'accepted'));
            $providerId = isset($data['id']) ? (string) $data['id'] : null;

            if (in_array($status, ['sent', 'delivered'], true)) {
                return DeliveryResult::sent($providerId);
            }

            return DeliveryResult::queued($providerId);
        }

        return DeliveryResult::failed(
            $this->describeError($data, $result['code']),
            meta: array_filter([
                'messagebird_code'    => $this->extractErrorCode($data),
                'messagebird_details' => is_array($data) ? ($data['details'] ?? null) : null,
            ]),
        );
    }

    public function getCredit(): ?string
    {
        // Bird does not document a public account balance endpoint for Channels API access keys.
        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        $accessKey = $this->getSharedConfig('access_key');
        $workspaceId = $this->getSharedConfig('workspace_id');

        if (!$accessKey || !$workspaceId) {
            return TestConnectionResult::error(__('Access Key and Workspace ID are required', 'wp-sms'));
        }

        $channelId = $this->firstConfiguredChannelId();
        if (!$channelId) {
            return TestConnectionResult::error(__('Configure at least one MessageBird Channel ID before testing the connection', 'wp-sms'));
        }

        $result = $this->httpGet($this->messagesUrl((string) $workspaceId, $channelId) . '?limit=1', [
            'headers' => $this->authHeaders((string) $accessKey, false),
        ]);

        if (!$result instanceof DeliveryResult && in_array($result['code'], [401, 403], true)) {
            return TestConnectionResult::error(__('Invalid MessageBird Access Key', 'wp-sms'));
        }

        $data = $this->validateTestResponse($result, 'MessageBird');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        return TestConnectionResult::ok(__('Connected to MessageBird Channels API', 'wp-sms'), [
            'workspace_id' => (string) $workspaceId,
            'channel_id'   => $channelId,
        ]);
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return $this->callbackUrl('status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyBirdSignature($request, $this->getStatusCallbackUrl());
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $events = $this->extractWebhookEvents($request);
        $updates = [];

        foreach ($events as $event) {
            $payload = $event['payload'] ?? $event;
            if (!is_array($payload)) {
                continue;
            }

            $messageId = (string) ($payload['messageId'] ?? $payload['id'] ?? '');
            if ($messageId === '') {
                continue;
            }

            $isOptOut = $this->payloadHasOptOutInteraction($payload);
            $status = $this->normalizeStatus((string) ($payload['status'] ?? ($isOptOut ? 'delivered' : '')));
            if ($status === '') {
                continue;
            }

            $failure = is_array($payload['failure'] ?? null) ? $payload['failure'] : [];
            $errorCode = $failure['code'] ?? $payload['reason'] ?? null;
            $errorMessage = $failure['description'] ?? $payload['details'] ?? null;

            $updates[] = new StatusUpdate(
                providerId: $messageId,
                status: $status,
                errorCode: $errorCode !== null && $errorCode !== '' ? (string) $errorCode : null,
                errorMessage: $errorMessage !== null && $errorMessage !== '' ? (string) $errorMessage : null,
                permanent: in_array($status, ['failed', 'deleted'], true),
                unsubscribe: $isOptOut,
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
        return $this->verifyBirdSignature($request, $this->getInboundCallbackUrl());
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $messages = [];

        foreach ($this->extractWebhookEvents($request) as $event) {
            $payload = $event['payload'] ?? $event;
            if (!is_array($payload)) {
                continue;
            }

            if (($payload['direction'] ?? 'incoming') !== 'incoming') {
                continue;
            }

            $from = $this->extractIdentifier($payload['sender'] ?? []);
            if ($from === '') {
                continue;
            }

            $messages[] = new InboundMessage(
                from: $from,
                to: $this->extractIdentifier($payload['receiver'] ?? []),
                body: $this->extractMessageText($payload['body'] ?? []),
                providerId: isset($payload['id']) ? (string) $payload['id'] : null,
                optOutType: $this->payloadHasOptOutInteraction($payload) ? 'unsubscribe-request' : null,
                meta: array_filter([
                    'event'      => $event['event'] ?? null,
                    'channel_id' => $payload['channelId'] ?? null,
                    'status'     => $payload['status'] ?? null,
                    'reference'  => $payload['reference'] ?? null,
                ]),
            );
        }

        return $messages;
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        $code = strtolower((string) ($result->meta['messagebird_code'] ?? ''));
        $details = strtolower($this->stringify($result->meta['messagebird_details'] ?? $result->error ?? ''));

        return str_contains($code, 'unsubscribe')
            || str_contains($code, 'suppression')
            || str_contains($details, 'unsubscribe')
            || str_contains($details, 'suppressed');
    }

    // --- SupportsTemplates ---

    public function requiresTemplateForChannel(string $channel): bool
    {
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
            'template' => array_filter([
                'name'       => $mapping->providerTemplateName ?: $mapping->providerTemplateId,
                'locale'     => $mapping->language ?: null,
                'parameters' => array_map(
                    fn($key, $value) => [
                        'type'  => 'string',
                        'key'   => (string) $key,
                        'value' => (string) $value,
                    ],
                    array_keys($resolvedVariables),
                    array_values($resolvedVariables),
                ),
            ]),
        ];
    }

    // --- Internal ---

    private function buildSendPayload(MessageInterface $message): array
    {
        $payload = [
            'receiver' => [
                'contacts' => [
                    [
                        'identifierKey'   => 'phonenumber',
                        'identifierValue' => $message->getRecipient(),
                    ],
                ],
            ],
            'reference' => $message->getFlowExecutionId()
                ?: $message->getCampaignId()
                ?: '',
        ];

        $meta = $message->getMeta();
        if (!empty($meta['template_mode']) && !empty($meta['provider_template_id'])) {
            $mapping = new TemplateMapping(
                templateType: '',
                providerTemplateId: (string) $meta['provider_template_id'],
                gatewayId: $this->getId(),
                language: (string) ($meta['template_language'] ?? $meta['locale'] ?? 'en'),
                variableMap: [],
                providerTemplateName: (string) ($meta['template_name'] ?? ''),
            );
            return array_merge($payload, $this->buildTemplatePayload($mapping, $meta['template_variables'] ?? []));
        }

        $templateType = $meta['template_type'] ?? null;
        if ($templateType && $this->catalogManager) {
            $mapping = $this->catalogManager->resolveMapping($templateType, $this->getId());
            if ($mapping) {
                $resolved = $mapping->resolveVariables($meta['template_variables'] ?? []);
                return array_merge($payload, $this->buildTemplatePayload($mapping, $resolved));
            }
        }

        $mediaUrls = array_values(array_filter((array) ($meta['media_urls'] ?? [])));
        if ($message->getChannel() === 'sms' && !empty($mediaUrls)) {
            $payload['body'] = [
                'type'  => 'image',
                'image' => array_filter([
                    'images' => array_map(fn($url) => ['mediaUrl' => (string) $url], array_slice($mediaUrls, 0, 10)),
                    'text'   => $message->getBody() !== '' ? $message->getBody() : null,
                ]),
            ];
            return $payload;
        }

        $payload['body'] = [
            'type' => 'text',
            'text' => [
                'text' => $message->getBody(),
            ],
        ];

        return $payload;
    }

    private function messagesUrl(string $workspaceId, string $channelId): string
    {
        return self::API_BASE
            . '/workspaces/' . rawurlencode($workspaceId)
            . '/channels/' . rawurlencode($channelId)
            . '/messages';
    }

    private function authHeaders(string $accessKey, bool $json = true): array
    {
        $headers = [
            'Authorization' => 'AccessKey ' . $accessKey,
            'Accept'        => 'application/json',
        ];

        if ($json) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }

    private function callbackUrl(string $kind): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/' . $kind);
    }

    private function firstConfiguredChannelId(): ?string
    {
        foreach ($this->getSupportedChannels() as $channel) {
            $channelId = $this->getChannelConfig($channel, 'channel_id');
            if ($channelId) {
                return (string) $channelId;
            }
        }

        return null;
    }

    private function verifyBirdSignature(\WP_REST_Request $request, string $url): bool
    {
        $signingKey = (string) $this->getSharedConfig('webhook_signing_key', '');
        $signature = (string) ($request->get_header('messagebird-signature') ?? '');
        $timestamp = (string) ($request->get_header('messagebird-request-timestamp') ?? '');

        if ($signingKey === '' || $signature === '' || $timestamp === '') {
            return false;
        }

        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false) {
            return false;
        }

        $body = (string) ($request->get_body() ?? '');
        $bodyChecksum = hash('sha256', $body, true);
        $payload = $timestamp . "\n" . $url . "\n" . $bodyChecksum;
        $expected = hash_hmac('sha256', $payload, $signingKey, true);

        return hash_equals($expected, $decodedSignature);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractWebhookEvents(\WP_REST_Request $request): array
    {
        $data = $request->get_json_params();
        if (!is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            return array_filter($data, 'is_array');
        }

        if (isset($data['payload']) && is_array($data['payload'])) {
            return [$data];
        }

        return [$data];
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'accepted', 'processing', 'scheduled', 'schedued' => 'queued',
            'sent'                                           => 'sent',
            'delivered', 'read'                              => 'delivered',
            'sending_failed', 'delivery_failed', 'skipped'   => 'failed',
            'deleted'                                        => 'deleted',
            default                                          => $status,
        };
    }

    private function describeError(mixed $data, int $httpCode): string
    {
        if (is_array($data)) {
            if (!empty($data['message']) && is_string($data['message'])) {
                return $data['message'];
            }

            if (!empty($data['detail']) && is_string($data['detail'])) {
                return $data['detail'];
            }

            if (!empty($data['title']) && is_string($data['title'])) {
                return $data['title'];
            }

            $errors = $data['errors'] ?? null;
            if (is_array($errors) && isset($errors[0]) && is_array($errors[0])) {
                return (string) ($errors[0]['message'] ?? $errors[0]['detail'] ?? "HTTP {$httpCode}");
            }
        }

        return "HTTP {$httpCode}";
    }

    private function extractErrorCode(mixed $data): ?string
    {
        if (!is_array($data)) {
            return null;
        }

        if (isset($data['code'])) {
            return (string) $data['code'];
        }

        if (isset($data['type'])) {
            return (string) $data['type'];
        }

        $errors = $data['errors'] ?? null;
        if (is_array($errors) && isset($errors[0]) && is_array($errors[0])) {
            $code = $errors[0]['code'] ?? $errors[0]['type'] ?? null;
            return $code !== null ? (string) $code : null;
        }

        return null;
    }

    private function extractIdentifier(mixed $node): string
    {
        if (!is_array($node)) {
            return '';
        }

        foreach (['contact', 'connector'] as $key) {
            if (isset($node[$key]) && is_array($node[$key])) {
                return (string) ($node[$key]['identifierValue'] ?? '');
            }
        }

        $contacts = $node['contacts'] ?? null;
        if (is_array($contacts) && isset($contacts[0]) && is_array($contacts[0])) {
            return (string) ($contacts[0]['identifierValue'] ?? '');
        }

        return (string) ($node['identifierValue'] ?? '');
    }

    private function extractMessageText(mixed $body): string
    {
        if (!is_array($body)) {
            return '';
        }

        if (isset($body['text']) && is_array($body['text'])) {
            return (string) ($body['text']['text'] ?? '');
        }

        if (isset($body['image']) && is_array($body['image'])) {
            return (string) ($body['image']['text'] ?? '');
        }

        return '';
    }

    private function payloadHasOptOutInteraction(array $payload): bool
    {
        $type = (string) ($payload['type'] ?? $payload['interactionType'] ?? '');
        return $type === 'unsubscribe-request';
    }

    private function stringify(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return wp_json_encode($value) ?: '';
    }
}

<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\SupportsInboundMessage;
use WSms\Messaging\Contracts\SupportsOptOutDetection;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\SupportsTemplates;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

/**
 * Telnyx — global multi-channel CPaaS.
 *
 * SMS/MMS via /v2/messages, WhatsApp via /v2/messages/whatsapp, RCS via /v2/messages/rcs.
 * All authed with `Authorization: Bearer <api_key>`. Status + inbound webhooks land on a
 * single URL and are signed with Ed25519 (header `telnyx-signature-ed25519` + `telnyx-timestamp`).
 *
 * TODO(verify): Telnyx exposes /v2/verifications/{sms,call,flashcall,whatsapp};
 * defer until SupportsVerify interface lands.
 *
 * TODO(voice): Telnyx /v2/calls is real but 'voice' is not in any provider's
 * getSupportedChannels() yet; cross-cutting plumbing required.
 *
 * TODO(flash_sms): no flash class exposed in /v2/messages — feature flag stays false.
 */
class TelnyxProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsOptOutDetection,
    SupportsDynamicOptions,
    SupportsTemplates
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.telnyx.com/v2';
    private const ERR_OPT_OUT = '40300';
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'telnyx';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp', 'rcs'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'placeholder' => 'KEY01...',
                    'required'    => true,
                    'description' => __('V2 API key from portal.telnyx.com → API Keys.', 'wp-sms'),
                ],
                'public_key' => [
                    'type'        => 'string',
                    'label'       => __('Webhook Public Key', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Ed25519 public key from Mission Control Portal → Account → Keys & Credentials. Required to verify status / inbound webhooks.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from_number' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => false,
                        'dynamic'     => true,
                        'description' => __('Pick a Telnyx number, or leave blank and provide a Messaging Profile ID below.', 'wp-sms'),
                    ],
                    'messaging_profile_id' => [
                        'type'        => 'string',
                        'label'       => __('Messaging Profile ID', 'wp-sms'),
                        'placeholder' => '40017a55-...',
                        'required'    => false,
                        'description' => __('Used as fallback when no sender number is selected; Telnyx picks a number from the profile.', 'wp-sms'),
                    ],
                ],
                'whatsapp' => [
                    'from_number' => [
                        'type'        => 'string',
                        'label'       => __('WhatsApp Sender', 'wp-sms'),
                        'placeholder' => '+14155550100',
                        'required'    => true,
                        'description' => __('Telnyx-linked WhatsApp Business number (Meta WABA must be linked to your Telnyx account).', 'wp-sms'),
                    ],
                ],
                'rcs' => [
                    'agent_id' => [
                        'type'        => 'string',
                        'label'       => __('RCS Agent ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Telnyx RCS agent UUID. Agent must be Brand-verified before live sends.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('Telnyx API key not configured', 'wp-sms'));
        }

        $channel = $message->getChannel();
        return match ($channel) {
            'sms'      => $this->sendSms($message, $apiKey),
            'whatsapp' => $this->sendWhatsapp($message, $apiKey),
            'rcs'      => $this->sendRcs($message, $apiKey),
            default    => DeliveryResult::failed(sprintf(__('Telnyx does not support channel %s', 'wp-sms'), $channel)),
        };
    }

    private function sendSms(MessageInterface $message, string $apiKey): DeliveryResult
    {
        $from = $this->getChannelConfig('sms', 'from_number');
        $messagingProfileId = $this->getChannelConfig('sms', 'messaging_profile_id');

        if (!$from && !$messagingProfileId) {
            return DeliveryResult::failed(__('Telnyx SMS requires a Sender Number or Messaging Profile ID', 'wp-sms'));
        }

        $body = [
            'to'          => $message->getRecipient(),
            'text'        => $message->getBody(),
            'webhook_url' => $this->getStatusCallbackUrl(),
            'auto_detect' => true,
        ];

        if ($from) {
            $body['from'] = $from;
        } else {
            $body['messaging_profile_id'] = $messagingProfileId;
        }

        $meta = $message->getMeta();
        $mediaUrls = $meta['media_urls'] ?? [];
        if (!empty($mediaUrls)) {
            $body['media_urls'] = array_values($mediaUrls);
            $body['type'] = 'MMS';
        }

        return $this->postMessage(self::API_BASE . '/messages', $body, $apiKey);
    }

    private function sendWhatsapp(MessageInterface $message, string $apiKey): DeliveryResult
    {
        $from = $this->getChannelConfig('whatsapp', 'from_number');
        if (!$from) {
            return DeliveryResult::failed(__('Telnyx WhatsApp Sender not configured', 'wp-sms'));
        }

        $body = [
            'from'        => $from,
            'to'          => $message->getRecipient(),
            'webhook_url' => $this->getStatusCallbackUrl(),
        ];

        $meta = $message->getMeta();
        $templatePayload = $this->resolveTemplatePayload($meta);
        if ($templatePayload !== null) {
            $body = array_merge($body, $templatePayload);
        } else {
            $body['text'] = $message->getBody();
        }

        return $this->postMessage(self::API_BASE . '/messages/whatsapp', $body, $apiKey);
    }

    private function sendRcs(MessageInterface $message, string $apiKey): DeliveryResult
    {
        $agentId = $this->getChannelConfig('rcs', 'agent_id');
        if (!$agentId) {
            return DeliveryResult::failed(__('Telnyx RCS Agent ID not configured', 'wp-sms'));
        }

        $body = [
            'agent_id'      => $agentId,
            'to'            => $message->getRecipient(),
            'agent_message' => [
                'content_message' => [
                    'text' => $message->getBody(),
                ],
            ],
            'webhook_url'   => $this->getStatusCallbackUrl(),
        ];

        return $this->postMessage(self::API_BASE . '/messages/rcs', $body, $apiKey);
    }

    private function postMessage(string $url, array $body, string $apiKey): DeliveryResult
    {
        $result = $this->httpPost($url, [
            'headers' => $this->authHeaders($apiKey),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true) ?: [];

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Telnyx API key', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300) {
            $providerId = $data['data']['id'] ?? null;
            return DeliveryResult::queued($providerId);
        }

        $errors = $data['errors'] ?? [];
        $first = $errors[0] ?? [];
        $errorCode = isset($first['code']) ? (string) $first['code'] : null;
        $errorMessage = $first['title'] ?? $first['detail'] ?? "HTTP {$result['code']}";

        return DeliveryResult::failed(
            $errorMessage,
            meta: array_filter([
                'telnyx_code'   => $errorCode,
                'telnyx_detail' => $first['detail'] ?? null,
            ]),
        );
    }

    private function resolveTemplatePayload(array $meta): ?array
    {
        if (!empty($meta['template_mode']) && !empty($meta['provider_template_id'])) {
            $mapping = new TemplateMapping(
                templateType: '',
                providerTemplateId: (string) $meta['provider_template_id'],
                gatewayId: $this->getId(),
                language: (string) ($meta['template_language'] ?? 'en_US'),
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

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/balance', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        $balance = $data['data']['balance'] ?? null;
        $currency = $data['data']['currency'] ?? 'USD';

        if ($balance === null) {
            return null;
        }

        return $balance . ' ' . $currency;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/balance', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Telnyx API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Telnyx');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = $data['data']['balance'] ?? 'N/A';
        $currency = $data['data']['currency'] ?? 'USD';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s %s', 'wp-sms'), $balance, $currency),
            ['balance' => $balance, 'currency' => $currency],
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyEd25519Signature($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $params = $request->get_json_params() ?: $request->get_params();
        $eventType = $params['data']['event_type'] ?? '';
        $payload = $params['data']['payload'] ?? [];

        $messageId = $payload['id'] ?? null;
        if (!$messageId || !$eventType) {
            return [];
        }

        if ($eventType === 'message.sent') {
            return [new StatusUpdate(
                providerId: (string) $messageId,
                status:     'sent',
            )];
        }

        if ($eventType !== 'message.finalized') {
            return [];
        }

        $updates = [];
        foreach ($payload['to'] ?? [] as $recipient) {
            $rawStatus = $recipient['status'] ?? '';
            $normalized = match ($rawStatus) {
                'delivered'             => 'delivered',
                'delivery_failed'       => 'failed',
                'delivery_unconfirmed'  => 'failed',
                'sending_failed'        => 'failed',
                default                 => $rawStatus ?: 'sent',
            };

            $errors = $payload['errors'] ?? [];
            $first = $errors[0] ?? [];
            $errorCode = isset($first['code']) ? (string) $first['code'] : null;

            $updates[] = new StatusUpdate(
                providerId:   (string) $messageId,
                status:       $normalized,
                errorCode:    $errorCode,
                errorMessage: $normalized === 'failed' && !empty($first['title'])
                    ? sprintf('Telnyx: %s', $first['title'])
                    : null,
                permanent:    $this->isPermanentError($errorCode),
            );
        }

        return $updates;
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyEd25519Signature($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $params = $request->get_json_params() ?: $request->get_params();
        $eventType = $params['data']['event_type'] ?? '';
        if ($eventType !== 'message.received') {
            return [];
        }

        $payload = $params['data']['payload'] ?? [];
        $from = $payload['from']['phone_number'] ?? '';
        if ($from === '') {
            return [];
        }

        $to = $payload['to'][0]['phone_number'] ?? '';

        $mediaUrls = [];
        foreach ($payload['media'] ?? [] as $media) {
            if (!empty($media['url'])) {
                $mediaUrls[] = $media['url'];
            }
        }

        return [new InboundMessage(
            from:       (string) $from,
            to:         (string) $to,
            body:       (string) ($payload['text'] ?? ''),
            providerId: $payload['id'] ?? null,
            meta:       array_filter([
                'media_urls' => $mediaUrls ?: null,
                'direction'  => $payload['direction'] ?? null,
            ]),
        )];
    }

    // --- SupportsTemplates ---

    public function requiresTemplateForChannel(string $channel): bool
    {
        // WhatsApp supports both free-form replies (24-hr session) and templates;
        // never force-fail at this layer.
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

        return [
            'template' => array_filter([
                'name'       => $mapping->providerTemplateId,
                'language'   => $mapping->language ? ['code' => $mapping->language] : null,
                'components' => [
                    ['type' => 'body', 'parameters' => $bodyParameters],
                ],
            ]),
        ];
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'from_number' || $section !== 'sms') {
            return [];
        }

        return $this->withConfig($config, function () {
            $apiKey = $this->getSharedConfig('api_key');
            if (!$apiKey) {
                return [];
            }

            $data = $this->fetchJsonOrFail(self::API_BASE . '/messaging_phone_numbers?page[size]=100', [
                'headers' => $this->authHeaders($apiKey),
            ]);

            $options = [];
            foreach ($data['data'] ?? [] as $number) {
                $phone = $number['phone_number'] ?? '';
                if (!$phone) {
                    continue;
                }
                $options[] = ['value' => $phone, 'label' => $phone];
            }

            return $options;
        });
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        $code = $result->meta['telnyx_code'] ?? null;
        return $code === self::ERR_OPT_OUT;
    }

    // --- Internal ---

    private function authHeaders(string $apiKey): array
    {
        return [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    /**
     * Verify the `telnyx-signature-ed25519` header against the public key configured
     * for this gateway. Signed payload is `{timestamp}|{raw_body}`.
     */
    private function verifyEd25519Signature(\WP_REST_Request $request): bool
    {
        $publicKey = $this->getSharedConfig('public_key');
        if (!$publicKey) {
            return false;
        }

        $signature = $request->get_header('telnyx-signature-ed25519');
        $timestamp = $request->get_header('telnyx-timestamp');
        if (empty($signature) || empty($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            return false;
        }

        $body = $request->get_body() ?? '';
        $signedPayload = $timestamp . '|' . $body;

        $rawSig = base64_decode($signature, true);
        $rawKey = base64_decode($publicKey, true);
        if ($rawSig === false || $rawKey === false) {
            return false;
        }

        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($rawSig, $signedPayload, $rawKey);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function isPermanentError(?string $code): bool
    {
        if ($code === null) {
            return false;
        }
        // Documented terminal errors per Telnyx error code reference.
        return in_array($code, [
            '40005',  // Invalid destination number
            '40010',  // Number does not support SMS
            self::ERR_OPT_OUT,  // Blocked due to STOP message
        ], true);
    }
}

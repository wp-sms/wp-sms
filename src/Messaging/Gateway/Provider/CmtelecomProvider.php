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
 * CM.com — Dutch CPaaS, global aggregator. Unified /v1.0/message endpoint
 * carries SMS, WhatsApp, and RCS; channel is selected via `allowedChannels`.
 *
 * Auth: X-CM-PRODUCTTOKEN header on every request.
 *
 * Webhooks: CM does not sign DLR / inbound payloads. We require a URL-token
 * query parameter (callback_secret) and reject by default if it's missing.
 */
// TODO(verify): CM.com exposes POST /otp/v2/otp/{id}/verify with full lifecycle.
//   Defer until WSMS adds SupportsVerify.
//
// TODO(voice): CM.com Voice API at api.cmtelecom.com/voiceapi/v2/.
//   Defer until WSMS adds the voice channel.
//
// TODO(template-fetch): GET /channels/v1/accounts/{accountId}/whatsapp/{channelRequestId}/templates
//   is available but needs an extra channelRequestId config field per WhatsApp channel.
//   Defer; operator can still map approved template names manually via the catalog.
final class CmtelecomProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsOptOutDetection,
    SupportsTemplates
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_URL    = 'https://gw.messaging.cm.com/v1.0/message';
    private const BALANCE_URL = 'https://api.cmtelecom.com/accountbalance/v1.0/accountbalance/%s';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'cmtelecom';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp', 'rcs'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'product_token' => [
                    'type'        => 'secret',
                    'required'    => true,
                    'label'       => __('Product Token', 'wp-sms'),
                    'description' => __('Product Token from Channels → API Settings.', 'wp-sms'),
                ],
                'account_id' => [
                    'type'        => 'string',
                    'required'    => false,
                    'label'       => __('Account ID', 'wp-sms'),
                    'description' => __('Required to display balance and run Test Connection.', 'wp-sms'),
                ],
                'callback_secret' => [
                    'type'        => 'secret',
                    'required'    => false,
                    'label'       => __('Callback Secret', 'wp-sms'),
                    'description' => __('URL-token shared with CM webhook. Required to accept DLR / inbound webhooks.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'required'    => true,
                        'label'       => __('Sender ID', 'wp-sms'),
                        'description' => __('Alphanumeric (≤11) or numeric.', 'wp-sms'),
                    ],
                ],
                'whatsapp' => [
                    'from' => [
                        'type'     => 'string',
                        'required' => true,
                        'label'    => __('WhatsApp Business Number', 'wp-sms'),
                    ],
                ],
                'rcs' => [
                    'from' => [
                        'type'     => 'string',
                        'required' => true,
                        'label'    => __('RCS Agent ID', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        return match ($message->getChannel()) {
            'sms'      => $this->sendUnified($message, 'SMS'),
            'whatsapp' => $this->sendUnified($message, 'WhatsApp'),
            'rcs'      => $this->sendUnified($message, 'RCS'),
            default    => DeliveryResult::failed(
                sprintf(__('CM.com does not support channel %s', 'wp-sms'), $message->getChannel())
            ),
        };
    }

    private function sendUnified(MessageInterface $message, string $cmChannel): DeliveryResult
    {
        $productToken = $this->getSharedConfig('product_token');
        if (!$productToken) {
            return DeliveryResult::failed(__('CM.com Product Token not configured', 'wp-sms'));
        }

        $channel = $message->getChannel();
        $from = $this->getChannelConfig($channel, 'from');
        if (!$from) {
            return DeliveryResult::failed(__('CM.com sender not configured for this channel', 'wp-sms'));
        }

        $reference = 'wsms-' . ($message->getFlowExecutionId() ?: uniqid('', true));

        $messageBlock = [
            'from'            => $from,
            'to'              => [['number' => $message->getRecipient()]],
            'reference'       => $reference,
            'allowedChannels' => [$cmChannel],
        ];

        // WhatsApp: try template payload first (richContent.conversation[].template.whatsapp).
        if ($channel === 'whatsapp') {
            $templatePayload = $this->resolveWhatsappTemplatePayload($message->getMeta());
            if ($templatePayload !== null) {
                $messageBlock['richContent'] = $templatePayload;
            } else {
                $messageBlock['body'] = ['type' => 'auto', 'content' => $message->getBody()];
            }
        } else {
            $messageBlock['body'] = ['type' => 'auto', 'content' => $message->getBody()];
        }

        $body = ['messages' => ['msg' => [$messageBlock]]];

        $result = $this->httpPost(self::SEND_URL, [
            'headers' => [
                'X-CM-PRODUCTTOKEN' => $productToken,
                'Content-Type'      => 'application/json',
                'Accept'            => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid CM.com Product Token', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300) {
            $messageDetails = $data['details'][0]['messageDetails'][0] ?? [];
            $providerId = $messageDetails['messageId'] ?? $reference;
            return DeliveryResult::queued((string) $providerId);
        }

        $errorCode = (string) (
            $data['details'][0]['messageDetails'][0]['messageErrorCode']
            ?? $data['details'][0]['errorCode']
            ?? $data['errorCode']
            ?? ''
        );

        return DeliveryResult::failed(
            $data['details'][0]['messageDetails'][0]['messageErrorDescription']
                ?? $data['details'][0]['errorDescription']
                ?? $data['errorMessage']
                ?? "HTTP {$result['code']}",
            meta: array_filter([
                'cm_code' => $errorCode !== '' ? $errorCode : null,
            ]),
        );
    }

    private function resolveWhatsappTemplatePayload(array $meta): ?array
    {
        // Direct template mode (caller passed CM-shaped richContent already).
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

    public function getCredit(): ?string
    {
        $accountId = $this->getSharedConfig('account_id');
        $productToken = $this->getSharedConfig('product_token');
        if (!$accountId || !$productToken) {
            // Account ID is required for the balance endpoint; return null silently otherwise.
            return null;
        }

        $result = $this->httpGet(sprintf(self::BALANCE_URL, rawurlencode($accountId)), [
            'headers' => [
                'X-CM-PRODUCTTOKEN' => $productToken,
                'Accept'            => 'application/json',
            ],
        ]);

        if ($result instanceof DeliveryResult || $result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['Amount'])) {
            return null;
        }

        $currency = $data['Currency'] ?? '';
        return trim(number_format((float) $data['Amount'], 2, '.', '') . ' ' . $currency);
    }

    public function testConnection(): TestConnectionResult
    {
        $accountId = $this->getSharedConfig('account_id');
        $productToken = $this->getSharedConfig('product_token');
        if (!$productToken) {
            return TestConnectionResult::error(__('Product Token is required', 'wp-sms'));
        }
        if (!$accountId) {
            return TestConnectionResult::error(__('Configure Account ID to test connection.', 'wp-sms'));
        }

        $result = $this->httpGet(sprintf(self::BALANCE_URL, rawurlencode($accountId)), [
            'headers' => [
                'X-CM-PRODUCTTOKEN' => $productToken,
                'Accept'            => 'application/json',
            ],
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Product Token or Account ID', 'wp-sms'));
            }
            if ($result['code'] === 404) {
                return TestConnectionResult::error(__('Account not found — check your Account ID', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'CM.com');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = isset($data['Amount']) ? trim(number_format((float) $data['Amount'], 2, '.', '') . ' ' . ($data['Currency'] ?? '')) : null;

        return TestConnectionResult::ok(
            $balance ? sprintf(__('Connected — Balance: %s', 'wp-sms'), $balance) : __('Connected to CM.com', 'wp-sms'),
            array_filter(['balance' => $balance]),
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyUrlToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $params = $request->get_json_params() ?: $request->get_params();

        $providerId = (string) ($params['reference'] ?? $params['messageId'] ?? '');
        if ($providerId === '') {
            return [];
        }

        $rawStatus = (int) ($params['status'] ?? -1);
        $errorCode = isset($params['errorCode']) ? (string) $params['errorCode'] : null;

        $normalized = match ($rawStatus) {
            0       => 'queued',
            1, 3    => 'failed',
            2, 4    => 'delivered',
            default => 'unknown',
        };

        $permanent = false;
        if ($rawStatus === 1) {
            // Rejected on submission → not retryable.
            $permanent = true;
        } elseif ($rawStatus === 3 && $errorCode !== null && in_array($errorCode, ['23', '37', '40'], true)) {
            $permanent = true;
        }

        return [new StatusUpdate(
            providerId:   $providerId,
            status:       $normalized,
            errorCode:    $errorCode,
            errorMessage: $normalized === 'failed' && !empty($params['statusDescription'])
                ? sprintf('CM.com: %s', $params['statusDescription'])
                : null,
            permanent:    $permanent,
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyUrlToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $params = $request->get_json_params() ?: $request->get_params();

        $from = (string) ($params['from'] ?? '');
        if ($from === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($params['to'] ?? ''),
            body:       (string) ($params['message'] ?? $params['body'] ?? ''),
            providerId: $params['reference'] ?? $params['messageId'] ?? null,
            meta:       array_filter([
                'channel'  => $params['channel'] ?? null,
                'time_utc' => $params['timeUtc'] ?? $params['timestamp'] ?? null,
            ]),
        )];
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        $code = $result->meta['cm_code'] ?? null;
        if ($code === null) {
            return false;
        }
        // 23 = blacklisted recipient, 37 = recipient on Do-Not-Call list.
        // Code 40 = conversation-window-closed (WhatsApp 24h) — NOT a true opt-out, excluded.
        return in_array((string) $code, ['23', '37'], true);
    }

    // --- SupportsTemplates ---

    public function requiresTemplateForChannel(string $channel): bool
    {
        // WhatsApp business-initiated messages require approved templates outside the
        // 24h session window, but free-form replies inside it are allowed; don't
        // force-fail at the gateway layer.
        return false;
    }

    public function getVariableStyle(): VariableStyle
    {
        return VariableStyle::Positional;
    }

    public function buildTemplatePayload(TemplateMapping $mapping, array $resolvedVariables): array
    {
        ksort($resolvedVariables, SORT_NATURAL);

        $parameters = [];
        foreach ($resolvedVariables as $value) {
            $parameters[] = ['type' => 'text', 'text' => (string) $value];
        }

        return [
            'conversation' => [[
                'template' => [
                    'whatsapp' => array_filter([
                        'namespace'  => $mapping->variableMap['namespace'] ?? null,
                        'name'       => $mapping->providerTemplateId,
                        'language'   => array_filter([
                            'policy' => 'deterministic',
                            'code'   => $mapping->language ?: null,
                        ]),
                        'components' => [
                            ['type' => 'body', 'parameters' => $parameters],
                        ],
                    ]),
                ],
            ]],
        ];
    }

    // --- Internal ---

    private function verifyUrlToken(\WP_REST_Request $request): bool
    {
        $expected = $this->getSharedConfig('callback_secret');
        if (!$expected) {
            return false;
        }
        $provided = (string) ($request->get_param('token') ?? '');
        if ($provided === '') {
            return false;
        }
        return hash_equals((string) $expected, $provided);
    }
}

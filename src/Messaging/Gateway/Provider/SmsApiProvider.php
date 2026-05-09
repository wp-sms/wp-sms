<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsCustomCallbackResponse;
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
 * SMSAPI (smsapi.com / smsapi.pl) — European SMS aggregator owned by LINK Mobility.
 *
 * Auth: Bearer token (modern SDK pattern; the legacy username/password auth was
 * deprecated by SMSAPI ~2018). Webhook callbacks are delivered as HTTP GET with
 * params in the query string — the receiver must reply with the literal body
 * "OK" (handled via SupportsCustomCallbackResponse). SMSAPI does not sign
 * callbacks; their built-in auth is a 5-IP allowlist that fails behind reverse
 * proxies, so this provider validates a configurable shared-secret query token
 * appended as ?token=... to the registered URL instead.
 */
class SmsApiProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsDynamicOptions,
    SupportsOptOutDetection,
    SupportsTemplates,
    SupportsCustomCallbackResponse
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const HOSTS = [
        'com' => 'https://api.smsapi.com',
        'pl'  => 'https://api.smsapi.pl',
    ];
    private const DEFAULT_REGION = 'com';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'smsapi';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'region' => [
                    'type'     => 'select',
                    'label'    => __('Region', 'wp-sms'),
                    'required' => true,
                    'default'  => self::DEFAULT_REGION,
                    'options'  => [
                        ['value' => 'com', 'label' => __('.com (global)', 'wp-sms')],
                        ['value' => 'pl',  'label' => __('.pl (Poland)', 'wp-sms')],
                    ],
                    'description' => __('Pick the SMSAPI host that matches your account: .com for global routing or .pl for the Polish portal.', 'wp-sms'),
                ],
                'api_token' => [
                    'type'        => 'secret',
                    'label'       => __('API Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Bearer token from Settings > API tokens. Grant scopes: sms, profile (and contacts for blacklist support).', 'wp-sms'),
                ],
                'callback_token' => [
                    'type'        => 'string',
                    'label'       => __('Callback Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Shared secret appended to the DLR/inbound URLs as ?token=… so the receiver can authenticate the webhook. SMSAPI does not sign callbacks, and their IP allowlist fails behind reverse proxies.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender Name', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Approved alphanumeric sender name. Leave blank to use the SMSAPI default short code.', 'wp-sms'),
                        'dynamic'     => true,
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiToken = $this->getSharedConfig('api_token');
        if (!$apiToken) {
            return DeliveryResult::failed(__('SMSAPI API token not configured', 'wp-sms'));
        }

        $body = [
            'to'       => $message->getRecipient(),
            'format'   => 'json',
            'encoding' => 'utf-8',
        ];

        $from = $this->getChannelConfig('sms', 'from');
        if ($from) {
            $body['from'] = $from;
        }

        $callbackUrl = $this->getStatusCallbackUrlWithToken();
        if ($callbackUrl !== null) {
            $body['notify_url'] = $callbackUrl;
        }

        $templatePayload = $this->resolveTemplatePayload($message->getMeta());
        if ($templatePayload !== null) {
            $body = array_merge($body, $templatePayload);
        } else {
            $body['message'] = $message->getBody();
        }

        $result = $this->httpPost($this->endpoint('/sms.do'), [
            'headers' => $this->authHeaders($apiToken) + [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
            ],
            'body' => http_build_query($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid SMSAPI token', 'wp-sms'));
        }

        // SMSAPI's error shape: {error: <int>, message: <string>}. The SDK
        // checks for the `error` key regardless of HTTP code.
        if (is_array($data) && isset($data['error'])) {
            return DeliveryResult::failed(
                $data['message'] ?? sprintf('SMSAPI error %s', (string) $data['error']),
                meta: array_filter(['smsapi_error' => (string) $data['error']]),
            );
        }

        if ($result['code'] >= 200 && $result['code'] < 300 && isset($data['list'][0])) {
            $entry = $data['list'][0];
            $providerId = isset($entry['id']) ? (string) $entry['id'] : null;
            $rawStatus = strtoupper((string) ($entry['status'] ?? ''));
            $points = isset($entry['points']) ? (float) $entry['points'] : null;
            $meta = array_filter(['smsapi_status' => $rawStatus ?: null]);

            if ($rawStatus === 'BLACKLIST') {
                return DeliveryResult::failed(
                    __('Recipient is on the SMSAPI blacklist', 'wp-sms'),
                    meta: $meta,
                );
            }

            if (in_array($rawStatus, ['UNDELIVERED', 'NOT_DELIVERED', 'FAILED', 'EXPIRED', 'REJECTED', 'INVALID_PHONE_NUMBER'], true)) {
                return DeliveryResult::failed(
                    sprintf(__('SMSAPI rejected the message: %s', 'wp-sms'), $rawStatus),
                    meta: $meta,
                );
            }

            if ($rawStatus === 'DELIVERED') {
                return DeliveryResult::sent($providerId, $points, $meta);
            }

            // QUEUE / SENT / unknown success → queued; final state arrives via DLR.
            return DeliveryResult::queued($providerId);
        }

        return DeliveryResult::failed(
            is_array($data) && isset($data['message']) ? (string) $data['message'] : sprintf('HTTP %d', $result['code']),
            meta: array_filter(['smsapi_code' => $result['code'] ?: null]),
        );
    }

    public function getCredit(): ?string
    {
        $apiToken = $this->getSharedConfig('api_token');
        if (!$apiToken) {
            return null;
        }

        $result = $this->httpGet($this->endpoint('/profile'), [
            'headers' => $this->authHeaders($apiToken),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['points'])) {
            return null;
        }

        return number_format((float) $data['points'], 2) . ' points';
    }

    public function testConnection(): TestConnectionResult
    {
        $apiToken = $this->getSharedConfig('api_token');
        if (!$apiToken) {
            return TestConnectionResult::error(__('API Token is required', 'wp-sms'));
        }

        $result = $this->httpGet($this->endpoint('/profile'), [
            'headers' => $this->authHeaders($apiToken),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid API Token', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'SMSAPI');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $points = $data['points'] ?? null;
        $message = $points !== null
            ? sprintf(__('Connected — Balance: %s points', 'wp-sms'), $points)
            : __('Connected', 'wp-sms');

        return TestConnectionResult::ok($message, ['balance' => $points]);
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'from' || $section !== 'sms') {
            return [];
        }

        return $this->withConfig($config, function () {
            $apiToken = $this->getSharedConfig('api_token');
            if (!$apiToken) {
                return [];
            }

            $data = $this->fetchJsonOrFail($this->endpoint('/sms/sendernames'), [
                'headers' => $this->authHeaders($apiToken),
            ]);

            // Response shape: {collection: [{name, status, default}, ...], size, ...}
            $entries = $data['collection'] ?? $data['list'] ?? (isset($data['name']) ? [$data] : []);
            if (!is_array($entries)) {
                return [];
            }

            $options = [];
            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $name = $entry['name'] ?? $entry['sender'] ?? null;
                if (!$name) {
                    continue;
                }
                $status = $entry['status'] ?? null;
                $label = $status ? sprintf('%s (%s)', $name, $status) : (string) $name;
                $options[] = ['value' => (string) $name, 'label' => $label];
            }
            return $options;
        });
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->validateCallbackToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $providerId = $request->get_param('MsgId');
        $rawStatus = (string) ($request->get_param('status_name') ?? $request->get_param('status') ?? '');

        if (empty($providerId) || $rawStatus === '') {
            return [];
        }

        $upper = strtoupper($rawStatus);
        $normalized = match ($upper) {
            'QUEUE'                                            => 'queued',
            'SENT'                                             => 'sent',
            'DELIVERED'                                        => 'delivered',
            'NOT_DELIVERED', 'UNDELIVERED', 'FAILED',
            'EXPIRED', 'REJECTED', 'INVALID_PHONE_NUMBER',
            'BLACKLIST'                                        => 'failed',
            default                                            => $rawStatus,
        };

        return [new StatusUpdate(
            providerId:   (string) $providerId,
            status:       $normalized,
            errorCode:    $upper !== '' ? $upper : null,
            errorMessage: $normalized === 'failed' ? sprintf('SMSAPI: %s', $rawStatus) : null,
            permanent:    in_array($upper, ['REJECTED', 'INVALID_PHONE_NUMBER', 'BLACKLIST'], true),
            unsubscribe:  $upper === 'BLACKLIST',
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->validateCallbackToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $from = (string) ($request->get_param('sms_from') ?? '');
        if ($from === '') {
            return [];
        }

        $msgId = $request->get_param('MsgId');

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($request->get_param('sms_to') ?? ''),
            body:       (string) ($request->get_param('sms_text') ?? ''),
            providerId: $msgId !== null ? (string) $msgId : null,
            meta:       array_filter([
                'sms_date' => $request->get_param('sms_date'),
            ]),
        )];
    }

    // --- SupportsCustomCallbackResponse ---

    public function getCallbackResponseBody(string $type, \WP_REST_Request $request): ?string
    {
        // SMSAPI requires the receiver to reply with the literal body "OK" — anything
        // else is treated as a delivery failure and the webhook will be retried.
        return 'OK';
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        $status = $result->meta['smsapi_status'] ?? null;
        return is_string($status) && strtoupper($status) === 'BLACKLIST';
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

        $payload = ['template' => $mapping->providerTemplateId];
        $position = 1;
        foreach ($resolvedVariables as $value) {
            if ($position > 4) {
                break; // SMSAPI accepts param1..param4 only.
            }
            $payload['param' . $position] = (string) $value;
            $position++;
        }
        return $payload;
    }

    // --- Internal ---

    protected function getApiHost(): string
    {
        $region = (string) $this->getSharedConfig('region', self::DEFAULT_REGION);
        return self::HOSTS[$region] ?? self::HOSTS[self::DEFAULT_REGION];
    }

    private function endpoint(string $path): string
    {
        return $this->getApiHost() . $path;
    }

    private function authHeaders(string $apiToken): array
    {
        return ['Authorization' => 'Bearer ' . $apiToken];
    }

    private function getStatusCallbackUrlWithToken(): ?string
    {
        $token = $this->getSharedConfig('callback_token');
        if (!$token) {
            return null;
        }
        $url = $this->getStatusCallbackUrl();
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 'token=' . rawurlencode((string) $token);
    }

    private function validateCallbackToken(\WP_REST_Request $request): bool
    {
        $expected = $this->getSharedConfig('callback_token');
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        $supplied = (string) ($request->get_param('token') ?? '');
        if ($supplied === '') {
            return false;
        }
        return hash_equals($expected, $supplied);
    }

    private function resolveTemplatePayload(array $meta): ?array
    {
        if (!empty($meta['template_mode']) && !empty($meta['provider_template_id'])) {
            $mapping = new TemplateMapping(
                templateType: '',
                providerTemplateId: (string) $meta['provider_template_id'],
                gatewayId: $this->getId(),
                language: '',
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
}

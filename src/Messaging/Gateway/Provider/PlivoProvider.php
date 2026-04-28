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

class PlivoProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsDynamicOptions,
    SupportsOptOutDetection,
    SupportsTemplates
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.plivo.com/v1/Account';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'plivo';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'auth_id' => [
                    'type'        => 'string',
                    'label'       => __('Auth ID', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Plivo Auth ID from the console (Account > Account Settings).', 'wp-sms'),
                    'placeholder' => 'MAxxxxxxxxxxxxxxxxxx',
                ],
                'auth_token' => [
                    'type'        => 'secret',
                    'label'       => __('Auth Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Plivo Auth Token from the console, shown next to the Auth ID.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from_number' => [
                        'type'        => 'string',
                        'label'       => __('From Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Your Plivo phone number in E.164 format (e.g., +15551234567).', 'wp-sms'),
                        'placeholder' => '+15551234567',
                        'dynamic'     => true,
                    ],
                ],
                'whatsapp' => [
                    'from_number' => [
                        'type'        => 'string',
                        'label'       => __('WhatsApp Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Your WhatsApp-enabled Plivo number in E.164 format. Provision under Messaging > WhatsApp in the Plivo console.', 'wp-sms'),
                        'placeholder' => '+15551234567',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $authId = $this->getSharedConfig('auth_id');
        $authToken = $this->getSharedConfig('auth_token');

        if (!$authId || !$authToken) {
            return DeliveryResult::failed(__('Plivo credentials not configured', 'wp-sms'));
        }

        $channel = $message->getChannel();
        $from = $this->getChannelConfig($channel, 'from_number');
        if (!$from) {
            return DeliveryResult::failed(__('Plivo From Number not configured for this channel', 'wp-sms'));
        }

        $meta = $message->getMeta();
        $mediaUrls = $meta['media_urls'] ?? [];

        $body = [
            'src'  => $from,
            'dst'  => $message->getRecipient(),
            'text' => $message->getBody(),
            'url'  => $this->getStatusCallbackUrl(),
        ];

        if ($channel === 'whatsapp') {
            $body['type'] = 'whatsapp';

            $templatePayload = $this->resolveTemplatePayload($meta);
            if ($templatePayload !== null) {
                $body = array_merge($body, $templatePayload);
            } elseif (!empty($meta['template'])) {
                $body['template'] = $meta['template'];
            }
        } elseif (!empty($mediaUrls)) {
            $body['type'] = 'mms';
        }

        if (!empty($mediaUrls)) {
            $body['media_urls'] = array_values($mediaUrls);
        }

        $url = self::API_BASE . "/{$authId}/Message/";

        $result = $this->httpPost($url, [
            'headers' => array_merge($this->authHeaders($authId, $authToken), [
                'Content-Type' => 'application/json',
            ]),
            'body' => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Plivo credentials', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300) {
            $messageUuids = $data['message_uuid'] ?? [];
            $providerId = $messageUuids[0] ?? null;

            return DeliveryResult::queued($providerId);
        }

        return DeliveryResult::failed(
            $data['error'] ?? $data['message'] ?? "HTTP {$result['code']}",
            meta: array_filter([
                'plivo_api_id'    => $data['api_id'] ?? null,
                'plivo_error_code' => isset($data['error_code']) ? (string) $data['error_code'] : null,
                'plivo_code'      => $result['code'] ?: null,
            ]),
        );
    }

    public function getCredit(): ?string
    {
        $authId = $this->getSharedConfig('auth_id');
        $authToken = $this->getSharedConfig('auth_token');

        if (!$authId || !$authToken) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . "/{$authId}/", [
            'headers' => $this->authHeaders($authId, $authToken),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        $credits = $data['cash_credits'] ?? null;
        if ($credits === null) {
            return null;
        }

        return number_format((float) $credits, 4) . ' USD';
    }

    public function testConnection(): TestConnectionResult
    {
        $authId = $this->getSharedConfig('auth_id');
        $authToken = $this->getSharedConfig('auth_token');

        if (!$authId || !$authToken) {
            return TestConnectionResult::error(__('Auth ID and Auth Token are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . "/{$authId}/", [
            'headers' => $this->authHeaders($authId, $authToken),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Auth ID or Auth Token', 'wp-sms'));
            }
            if ($result['code'] === 404) {
                return TestConnectionResult::error(__('Account not found — check your Auth ID', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Plivo');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $credits = $data['cash_credits'] ?? 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s USD', 'wp-sms'), $credits),
            ['balance' => $credits],
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyPlivoSignature($request, $this->getStatusCallbackUrl());
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $messageUuid = $request->get_param('MessageUUID');
        $rawStatus = $request->get_param('Status');

        if (empty($messageUuid) || empty($rawStatus)) {
            return [];
        }

        $normalized = match ($rawStatus) {
            'queued'           => 'queued',
            'sent'             => 'sent',
            'delivered', 'read' => 'delivered',
            'undelivered', 'failed', 'rejected' => 'failed',
            default            => $rawStatus,
        };

        $errorCode = $request->get_param('ErrorCode');

        return [new StatusUpdate(
            providerId:   (string) $messageUuid,
            status:       $normalized,
            errorCode:    $errorCode ? (string) $errorCode : null,
            errorMessage: $normalized === 'failed' ? sprintf('Plivo: %s', $rawStatus) : null,
            permanent:    $this->isPermanentPlivoError((string) $errorCode),
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyPlivoSignature($request, $this->getInboundCallbackUrl());
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $from = $request->get_param('From');
        if (empty($from)) {
            return [];
        }

        $mediaUrls = [];
        for ($i = 0; $i < 10; $i++) {
            $url = $request->get_param("Media{$i}");
            if ($url) {
                $mediaUrls[] = $url;
            }
        }

        return [new InboundMessage(
            from:       (string) $from,
            to:         (string) ($request->get_param('To') ?? ''),
            body:       (string) ($request->get_param('Text') ?? ''),
            providerId: $request->get_param('MessageUUID'),
            meta:       array_filter([
                'type'       => $request->get_param('Type'),
                'media_urls' => $mediaUrls ?: null,
            ]),
        )];
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'from_number' || $section !== 'sms') {
            return [];
        }

        return $this->withConfig($config, function () {
            $authId = $this->getSharedConfig('auth_id');
            $authToken = $this->getSharedConfig('auth_token');

            $data = $this->fetchJsonOrFail(
                self::API_BASE . "/{$authId}/Number/?limit=20",
                ['headers' => $this->authHeaders($authId, $authToken)],
            );

            $options = [];
            foreach ($data['objects'] ?? [] as $number) {
                $phoneNumber = $number['number'] ?? '';
                if (!$phoneNumber) {
                    continue;
                }
                $phoneNumber = '+' . ltrim($phoneNumber, '+');

                $caps = [];
                if (!empty($number['sms_enabled'])) $caps[] = 'SMS';
                if (!empty($number['mms_enabled'])) $caps[] = 'MMS';
                if (!empty($number['voice_enabled'])) $caps[] = 'Voice';

                $label = $caps
                    ? sprintf('%s (%s)', $phoneNumber, implode(', ', $caps))
                    : $phoneNumber;

                $options[] = ['value' => $phoneNumber, 'label' => $label];
            }

            return $options;
        });
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        // Plivo error code 200 = "Recipient on do-not-call list / opted out".
        // Captured from response body's error_code (DLR callbacks deliver the same code).
        $code = $result->meta['plivo_error_code'] ?? null;
        return $code === '200';
    }

    // --- SupportsTemplates ---

    public function requiresTemplateForChannel(string $channel): bool
    {
        // WhatsApp business-initiated messages require approved templates;
        // user-initiated 24-hour-window replies don't, so don't force-fail.
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
                'language'   => $mapping->language ?: null,
                'components' => [
                    ['type' => 'body', 'parameters' => $bodyParameters],
                ],
            ]),
        ];
    }

    private function resolveTemplatePayload(array $meta): ?array
    {
        // Direct template mode (flow builder picked a template explicitly).
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

    // --- Internal ---

    private function authHeaders(string $authId, string $authToken): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode("{$authId}:{$authToken}"),
            'Accept'        => 'application/json',
        ];
    }

    /**
     * Verify the X-Plivo-Signature-V3 header per Plivo's spec.
     *
     * Algorithm (POST):
     *   base = scheme://host[:port]/path
     *   if existing query: base .= '?' . sortedQueryString
     *                       if has-post-params: base .= '.'
     *   else if has-post-params: base .= '?'
     *   if has-post-params: base .= concat(sorted(k . v))
     *   sig = base64(HMAC-SHA256(authToken, base . '.' . nonce))
     *
     * Plivo can send a comma-separated list of signatures (multiple active auth tokens).
     */
    private function verifyPlivoSignature(\WP_REST_Request $request, string $callbackUrl): bool
    {
        $authToken = $this->getSharedConfig('auth_token');
        if (!$authToken) {
            return false;
        }

        $signatureHeader = $request->get_header('x-plivo-signature-v3');
        $nonce = $request->get_header('x-plivo-signature-v3-nonce');
        if (empty($signatureHeader) || empty($nonce)) {
            return false;
        }

        $params = $request->get_params();
        unset($params['gateway_id']);

        $method = strtoupper($request->get_method() ?: 'POST');
        $expected = $this->computeV3Signature($method, $callbackUrl, $nonce, $authToken, $params);

        foreach (explode(',', $signatureHeader) as $candidate) {
            if (hash_equals($expected, trim($candidate))) {
                return true;
            }
        }
        return false;
    }

    private function computeV3Signature(string $method, string $url, string $nonce, string $authToken, array $params): string
    {
        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '/';
        $existingQuery = $parsed['query'] ?? '';

        $base = "{$scheme}://{$host}{$port}{$path}";

        if ($method === 'GET') {
            $merged = $params;
            if ($existingQuery !== '') {
                parse_str($existingQuery, $existingParams);
                $merged = array_merge($existingParams, $merged);
            }
            ksort($merged, SORT_STRING);
            $queryString = http_build_query($merged, '', '&', PHP_QUERY_RFC3986);
            $canonical = $queryString ? $base . '?' . $queryString : $base;
        } else {
            $hasParams = !empty($params);
            if ($existingQuery !== '') {
                $canonical = $base . '?' . $this->sortedQueryString($existingQuery);
                if ($hasParams) {
                    $canonical .= '.';
                }
            } else {
                $canonical = $hasParams ? ($base . '?') : $base;
            }
            if ($hasParams) {
                $canonical .= $this->sortedParamsConcat($params);
            }
        }

        $stringToSign = $canonical . '.' . $nonce;
        return base64_encode(hash_hmac('sha256', $stringToSign, $authToken, true));
    }

    private function sortedQueryString(string $query): string
    {
        parse_str($query, $parsed);
        ksort($parsed, SORT_STRING);
        return http_build_query($parsed, '', '&', PHP_QUERY_RFC3986);
    }

    private function sortedParamsConcat(array $params): string
    {
        ksort($params, SORT_STRING);
        $out = '';
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $sorted = $value;
                sort($sorted, SORT_STRING);
                foreach ($sorted as $v) {
                    $out .= $key . $this->stringifyValue($v);
                }
            } else {
                $out .= $key . $this->stringifyValue($value);
            }
        }
        return $out;
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return wp_json_encode($value);
        }
        return (string) $value;
    }

    private function isPermanentPlivoError(string $code): bool
    {
        if ($code === '') {
            return false;
        }
        // Plivo error codes that indicate permanent failure (won't retry).
        // 130: Invalid destination number, 132: Unknown destination handset,
        // 138: Subscriber absent (long-term), 140: Number blocked by carrier.
        return in_array($code, ['130', '132', '138', '140'], true);
    }
}

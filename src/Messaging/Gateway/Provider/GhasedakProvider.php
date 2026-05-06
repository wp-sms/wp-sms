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

/**
 * Ghasedak — Iranian SMS provider with two parallel JSON REST APIs.
 *
 * The merchant must pick one of two backends in the gateway config; both speak
 * JSON over HTTPS and authenticate with an `ApiKey` request header.
 *
 *   New API (ghasedak.me):
 *     Send:     POST /rest/api/v1/WebService/SendSingleSMS
 *               { lineNumber, message, receptor }
 *     Template: POST /rest/api/v1/WebService/SendOtpSMS
 *               { receptors:[{mobile,clientReferenceId}], templateName, inputs:[{param,value}] }
 *     Balance:  GET  /rest/api/v1/WebService/GetAccountInformation
 *
 *   Legacy API (ghasedaksms.com):
 *     Send:     POST /api/v1/Send/Simple
 *               { sender, message, receptor }
 *     Template: POST /api/v1/Send/NewOTP
 *               { receptor, type, template, allparam:[{param,value}] }
 *     Balance:  GET  /api/v1/Account/AccountInfo
 *
 * Both endpoints share the response envelope { isSuccess, message, data }.
 * Templates use named variables (e.g. `%order_id%`), so the variable style is
 * Named, and template payloads emit the {param,value} pairs both APIs expect.
 *
 * Out of scope: bulk sending (single recipient only), MMS, status webhooks,
 * inbound webhooks, the legacy numeric error-code translation table from v7
 * (the new envelope's `message` field already carries a localised description).
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class GhasedakProvider extends AbstractProvider implements SupportsTemplates
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE_NEW    = 'https://gateway.ghasedak.me/';
    private const API_BASE_LEGACY = 'https://gateway.ghasedaksms.com/';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'ghasedak';
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
                    'description' => __('Your Ghasedak API key, generated in the panel settings', 'wp-sms'),
                ],
                'api_type' => [
                    'type'        => 'select',
                    'label'       => __('API Variant', 'wp-sms'),
                    'required'    => true,
                    'description' => __('New API requires whitelisting your server IP when generating the API key; legacy does not.', 'wp-sms'),
                    'options'     => [
                        'ghasedak.me'     => __('New API (ghasedak.me)', 'wp-sms'),
                        'ghasedaksms.com' => __('Legacy API (ghasedaksms.com)', 'wp-sms'),
                    ],
                    'default'     => 'ghasedak.me',
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the Ghasedak panel', 'wp-sms'),
                        'placeholder' => '50002178584000',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return DeliveryResult::failed(__('Ghasedak credentials not configured', 'wp-sms'));
        }

        $meta = $message->getMeta();

        // Flow direct template path — provider template ID supplied in meta.
        if ($meta['template_mode'] ?? false) {
            return $this->sendTemplate(
                $apiKey,
                $message->getRecipient(),
                (string) ($meta['provider_template_id'] ?? ''),
                $meta['template_variables'] ?? [],
            );
        }

        // System template path — resolve mapping via catalog manager.
        $templateType = $meta['template_type'] ?? null;
        if ($templateType && $this->catalogManager) {
            $mapping = $this->catalogManager->resolveMapping($templateType, $this->getId());
            if ($mapping) {
                $resolved = $mapping->resolveVariables($meta['template_variables'] ?? []);
                return $this->sendTemplate(
                    $apiKey,
                    $message->getRecipient(),
                    $mapping->providerTemplateId,
                    $resolved,
                );
            }
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('Ghasedak sender not configured', 'wp-sms'));
        }

        $isNewApi = $this->isNewApi();
        $url      = $this->resolveEndpoint('send');

        $payload = $isNewApi
            ? ['lineNumber' => $sender, 'message' => $message->getBody(), 'receptor' => $message->getRecipient()]
            : ['sender' => $sender, 'message' => $message->getBody(), 'receptor' => $message->getRecipient()];

        $result = $this->httpPost($url, [
            'headers' => $this->buildHeaders($apiKey),
            'body'    => wp_json_encode($payload),
        ]);

        return $this->parseResponse($result);
    }

    private function sendTemplate(string $apiKey, string $recipient, string $templateName, array $resolvedVariables): DeliveryResult
    {
        $isNewApi = $this->isNewApi();
        $url      = $this->resolveEndpoint('template');

        if ($isNewApi) {
            $payload = [
                'receptors'    => [[
                    'mobile'            => $recipient,
                    'clientReferenceId' => '1',
                ]],
                'templateName' => $templateName,
                'inputs'       => $this->packVariableParams($resolvedVariables),
            ];
        } else {
            $payload = [
                'receptor' => $recipient,
                'type'     => '1',
                'template' => $templateName,
                'allparam' => $this->packVariableParams($resolvedVariables),
            ];
        }

        $result = $this->httpPost($url, [
            'headers' => $this->buildHeaders($apiKey),
            'body'    => wp_json_encode($payload),
        ]);

        return $this->parseResponse($result);
    }

    private function parseResponse(array|DeliveryResult $result): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(__('Invalid response from Ghasedak', 'wp-sms'));
        }

        if (($data['isSuccess'] ?? false) !== true) {
            return DeliveryResult::failed((string) ($data['message'] ?? __('Ghasedak send failed', 'wp-sms')));
        }

        // The new API returns either a scalar message id or an array of items in `data`.
        $providerId = '';
        $payload    = $data['data'] ?? null;
        if (is_scalar($payload)) {
            $providerId = (string) $payload;
        } elseif (is_array($payload)) {
            $first = reset($payload);
            if (is_scalar($first)) {
                $providerId = (string) $first;
            } elseif (is_array($first)) {
                $providerId = (string) ($first['messageId'] ?? $first['id'] ?? '');
            }
        }

        return DeliveryResult::sent($providerId);
    }

    public function getCredit(): ?string
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return null;
        }

        $result = $this->httpGet($this->resolveEndpoint('balance'), [
            'headers' => $this->buildHeaders($apiKey),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || ($data['isSuccess'] ?? false) !== true) {
            return null;
        }

        $credit = $data['data']['balance'] ?? $data['data']['credit'] ?? null;
        return $credit !== null ? (string) $credit : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet($this->resolveEndpoint('balance'), [
            'headers' => $this->buildHeaders($apiKey),
        ]);

        $data = $this->validateTestResponse($result, 'Ghasedak');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (($data['isSuccess'] ?? false) !== true) {
            $message = (string) ($data['message'] ?? __('Unknown error', 'wp-sms'));
            return TestConnectionResult::error($message);
        }

        $credit = $data['data']['balance'] ?? $data['data']['credit'] ?? 'N/A';
        $credit = (string) $credit;

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }

    // --- SupportsTemplates ---

    public function requiresTemplateForChannel(string $channel): bool
    {
        return false;
    }

    public function getVariableStyle(): VariableStyle
    {
        return VariableStyle::Named;
    }

    public function buildTemplatePayload(TemplateMapping $mapping, array $resolvedVariables): array
    {
        return [
            'endpoint'     => $this->isNewApi() ? 'SendOtpSMS' : 'NewOTP',
            'templateName' => $mapping->providerTemplateId,
            'inputs'       => $this->packVariableParams($resolvedVariables),
        ];
    }

    private function isNewApi(): bool
    {
        return $this->getSharedConfig('api_type', 'ghasedak.me') !== 'ghasedaksms.com';
    }

    private function resolveEndpoint(string $type): string
    {
        $isNewApi = $this->isNewApi();
        $base     = $isNewApi ? self::API_BASE_NEW : self::API_BASE_LEGACY;

        $paths = [
            'send'     => $isNewApi ? 'rest/api/v1/WebService/SendSingleSMS' : 'api/v1/Send/Simple',
            'template' => $isNewApi ? 'rest/api/v1/WebService/SendOtpSMS'    : 'api/v1/Send/NewOTP',
            'balance'  => $isNewApi ? 'rest/api/v1/WebService/GetAccountInformation' : 'api/v1/Account/AccountInfo',
        ];

        return $base . ($paths[$type] ?? '');
    }

    private function buildHeaders(string $apiKey): array
    {
        return [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'ApiKey'       => $apiKey,
        ];
    }

    /**
     * Map named variables to Ghasedak's [{param, value}, ...] structure.
     * Both API variants accept the same shape under different envelope keys.
     */
    private function packVariableParams(array $resolvedVariables): array
    {
        $items = [];
        foreach ($resolvedVariables as $key => $value) {
            $items[] = [
                'param' => (string) $key,
                'value' => (string) $value,
            ];
        }
        return $items;
    }
}

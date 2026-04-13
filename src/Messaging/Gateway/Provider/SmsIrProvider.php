<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\SupportsTemplates;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

class SmsIrProvider extends AbstractProvider implements SupportsDynamicOptions, SupportsTemplates
{
    private const API_BASE = 'https://api.sms.ir/v1';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'smsir';
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
                    'description' => __('Your API key from the SMS.ir panel under Developer API section', 'wp-sms'),
                    'placeholder' => 'Your SMS.ir API key',
                ],
            ],
            'channels' => [
                'sms' => [
                    'line_number' => [
                        'type'        => 'string',
                        'label'       => __('Line Number', 'wp-sms'),
                        'required'    => true,
                        'dynamic'     => true,
                        'description' => __('Your dedicated sender line number from the SMS.ir panel', 'wp-sms'),
                        'placeholder' => '30001234567890',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');

        if (!$apiKey) {
            return DeliveryResult::failed(__('SMS.ir credentials not configured', 'wp-sms'));
        }

        $meta = $message->getMeta();

        // Flow direct template path — template already resolved
        if ($meta['template_mode'] ?? false) {
            return $this->sendVerify(
                $apiKey,
                $message->getRecipient(),
                $meta['provider_template_id'],
                $meta['template_variables'] ?? [],
            );
        }

        // System template path — resolve mapping from catalog
        $templateType = $meta['template_type'] ?? null;
        if ($templateType && $this->catalogManager) {
            $mapping = $this->catalogManager->resolveMapping($templateType, $this->getId());
            if ($mapping) {
                $resolved = $mapping->resolveVariables($meta['template_variables'] ?? []);
                return $this->sendVerify(
                    $apiKey,
                    $message->getRecipient(),
                    $mapping->providerTemplateId,
                    $resolved,
                );
            }
        }

        // Raw SMS send (no template)
        $lineNumber = $this->getChannelConfig('sms', 'line_number');
        if (!$lineNumber) {
            return DeliveryResult::failed(__('SMS.ir line number not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/send/bulk', [
            'headers' => $this->apiHeaders($apiKey),
            'body' => wp_json_encode([
                'lineNumber'  => $lineNumber,
                'messageText' => $message->getBody(),
                'mobiles'     => [$message->getRecipient()],
            ]),
        ]);

        return $this->parseBulkResponse($result);
    }

    /**
     * Send via SMS.ir verify endpoint (template-based).
     */
    private function sendVerify(string $apiKey, string $recipient, string $templateId, array $resolvedVariables): DeliveryResult
    {
        $parameters = [];
        foreach ($resolvedVariables as $name => $value) {
            $parameters[] = ['name' => (string) $name, 'value' => (string) $value];
        }

        $result = $this->httpPost(self::API_BASE . '/send/verify', [
            'headers' => $this->apiHeaders($apiKey),
            'body' => wp_json_encode([
                'mobile'     => $recipient,
                'templateId' => (int) $templateId,
                'parameters' => $parameters,
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if (($data['status'] ?? 0) === 1) {
            $messageId = $data['data']['messageId'] ?? null;
            return DeliveryResult::sent(
                providerId: $messageId !== null ? (string) $messageId : null,
                cost: isset($data['data']['cost']) ? (float) $data['data']['cost'] : null,
            );
        }

        return DeliveryResult::failed($data['message'] ?? __('SMS.ir verify send failed', 'wp-sms'));
    }

    private function parseBulkResponse(array|DeliveryResult $result): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if (($data['status'] ?? 0) === 1) {
            $messageId = $data['data']['messageIds'][0] ?? null;
            return DeliveryResult::sent(
                providerId: $messageId !== null ? (string) $messageId : null,
                cost: isset($data['data']['cost']) ? (float) $data['data']['cost'] : null,
            );
        }

        return DeliveryResult::failed($data['message'] ?? __('SMS.ir send failed', 'wp-sms'));
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/credit', [
            'headers' => [
                'X-API-KEY' => $apiKey,
                'Accept'    => 'application/json',
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);

        if (($data['status'] ?? 0) === 1) {
            return (string) ($data['data'] ?? '');
        }

        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');

        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/credit', [
            'headers' => [
                'X-API-KEY' => $apiKey,
                'Accept'    => 'application/json',
            ],
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'SMS.ir');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (($data['status'] ?? 0) !== 1) {
            return TestConnectionResult::error($data['message'] ?? __('Unknown error', 'wp-sms'));
        }

        $credit = (string) ($data['data'] ?? 'N/A');

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'line_number') {
            return [];
        }

        return $this->withConfig($config, function () {
            $apiKey = $this->getSharedConfig('api_key');
            $data = $this->fetchJsonOrFail(self::API_BASE . '/line', [
                'headers' => [
                    'X-API-KEY' => $apiKey,
                    'Accept'    => 'application/json',
                ],
            ]);

            $lines = $data['data'] ?? [];
            $options = [];

            foreach ($lines as $line) {
                $lineStr = (string) $line;
                $options[] = ['value' => $lineStr, 'label' => $lineStr];
            }

            return $options;
        });
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
        $parameters = [];
        foreach ($resolvedVariables as $name => $value) {
            $parameters[] = ['name' => (string) $name, 'value' => (string) $value];
        }

        return [
            'endpoint'   => 'send/verify',
            'templateId' => (int) $mapping->providerTemplateId,
            'parameters' => $parameters,
        ];
    }

    private function apiHeaders(string $apiKey): array
    {
        return [
            'X-API-KEY'    => $apiKey,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }
}

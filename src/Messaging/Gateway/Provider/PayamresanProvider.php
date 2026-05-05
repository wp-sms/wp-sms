<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\ProviderTemplate;
use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\TemplateStatus;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\SupportsTemplateFetch;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

class PayamresanProvider extends AbstractProvider implements SupportsDynamicOptions, SupportsTemplateFetch
{
    private const API_BASE = 'https://api.sms-webservice.com/api/V3';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'payamresan';
    }

    public function getName(): string
    {
        return 'Payamresan';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getMetadata(): array
    {
        return [
            'description' => __('Iranian SMS gateway with REST V3 API, template (pattern) messaging, sender-line management, and balance lookup.', 'wp-sms'),
            'website'     => 'https://payam-resan.com/',
            'regions'     => ['middle-east'],
            'setup_url'   => 'https://payam-resan.com/',
            'setup_notes' => [
                __('Sign up at payam-resan.com and complete KYC (Iranian national ID is typically required).', 'wp-sms'),
                __('Open the Web Service / Developer section in the user panel and generate an API key.', 'wp-sms'),
                __('Approved sender numbers appear automatically in the Sender field once the API key is saved.', 'wp-sms'),
                __('Templates (patterns) must be approved in the panel before SendToken endpoints will accept them.', 'wp-sms'),
            ],
        ];
    }

    public function getFeatures(): array
    {
        return [
            'delivery_receipt' => false,
            'incoming'         => false,
            'unicode'          => true,
            'test_connection'  => true,
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your API key from the Payamresan panel under the Web Service / Developer section.', 'wp-sms'),
                    'placeholder' => 'Your Payamresan API key',
                ],
            ],
            'channels' => [
                'sms' => [
                    'from_number' => [
                        'type'        => 'string',
                        'label'       => __('Sender', 'wp-sms'),
                        'required'    => true,
                        'dynamic'     => true,
                        'description' => __('Approved sender line from your Payamresan account.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('Payamresan API key not configured', 'wp-sms'));
        }

        $sender = $this->getChannelConfig('sms', 'from_number');
        if (!$sender) {
            return DeliveryResult::failed(__('Payamresan sender is not configured', 'wp-sms'));
        }

        $meta = $message->getMeta();

        // Direct template path — template id and variables are pre-resolved on the message.
        if ($meta['template_mode'] ?? false) {
            return $this->sendToken(
                $apiKey,
                $sender,
                $message->getRecipient(),
                (string) $meta['provider_template_id'],
                $meta['template_variables'] ?? [],
            );
        }

        // System template path — resolve via catalog.
        $templateType = $meta['template_type'] ?? null;
        if ($templateType && $this->catalogManager) {
            $mapping = $this->catalogManager->resolveMapping($templateType, $this->getId());
            if ($mapping) {
                $resolved = $mapping->resolveVariables($meta['template_variables'] ?? []);
                return $this->sendToken(
                    $apiKey,
                    $sender,
                    $message->getRecipient(),
                    $mapping->providerTemplateId,
                    $resolved,
                );
            }
        }

        return $this->sendFreeText($apiKey, $sender, $message->getRecipient(), $message->getBody());
    }

    private function sendFreeText(string $apiKey, string $sender, string $recipient, string $body): DeliveryResult
    {
        $url = add_query_arg([
            'ApiKey'        => $apiKey,
            'Sender'        => $sender,
            'Recipients'    => $recipient,
            'MessageBodies' => $body,
        ], self::API_BASE . '/Send');

        $result = $this->httpGet($url, [
            'headers' => ['Accept' => 'application/json'],
        ]);

        return $this->parseSendResponse($result);
    }

    private function sendToken(string $apiKey, string $sender, string $recipient, string $templateKey, array $resolvedVariables): DeliveryResult
    {
        $payload = [
            'ApiKey'      => $apiKey,
            'Sender'      => $sender,
            'Recipient'   => $recipient,
            'TemplateKey' => $templateKey,
        ];

        foreach ($this->positionalParameters($resolvedVariables) as $position => $value) {
            $payload['p' . $position] = $value;
        }

        $result = $this->httpPost(self::API_BASE . '/SendTokenSingle', [
            'headers' => $this->jsonHeaders(),
            'body'    => wp_json_encode($payload),
        ]);

        return $this->parseSendResponse($result);
    }

    private function parseSendResponse(array|DeliveryResult $result): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if (!is_array($data) || !($data['Success'] ?? false)) {
            $error = $data['Error'] ?? sprintf(__('Payamresan send failed (HTTP %d)', 'wp-sms'), $result['code']);
            $code = $data['ErrorCode'] ?? null;
            if ($code !== null) {
                $error = sprintf('ErrorCode %s: %s', $code, $error);
            }
            return DeliveryResult::failed($error);
        }

        $messageId = null;
        $resultPayload = $data['Result'] ?? null;
        if (is_array($resultPayload)) {
            if (isset($resultPayload[0]['Id'])) {
                $messageId = (string) $resultPayload[0]['Id'];
            } elseif (isset($resultPayload['Id'])) {
                $messageId = (string) $resultPayload['Id'];
            }
        }

        return DeliveryResult::sent(providerId: $messageId);
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpPost(self::API_BASE . '/AccountInfo', [
            'headers' => $this->jsonHeaders(),
            'body'    => wp_json_encode(['ApiKey' => $apiKey]),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !($data['Success'] ?? false)) {
            return null;
        }

        $credit = $data['Result']['Credit'] ?? null;
        return $credit !== null ? (string) $credit : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/AccountInfo', [
            'headers' => $this->jsonHeaders(),
            'body'    => wp_json_encode(['ApiKey' => $apiKey]),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Payamresan');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (!($data['Success'] ?? false)) {
            return TestConnectionResult::error($data['Error'] ?? __('Unknown error', 'wp-sms'));
        }

        $credit = (string) ($data['Result']['Credit'] ?? 'N/A');

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'from_number') {
            return [];
        }

        return $this->withConfig($config, function () {
            $apiKey = $this->getSharedConfig('api_key');
            if (!$apiKey) {
                throw new \RuntimeException(__('API Key is required', 'wp-sms'));
            }

            $data = $this->postJsonOrFail(self::API_BASE . '/AccountInfo', ['ApiKey' => $apiKey]);

            $senders = $data['Result']['AvailableSenders'] ?? [];
            $options = [];
            foreach ($senders as $sender) {
                $value = (string) $sender;
                $options[] = ['value' => $value, 'label' => $value];
            }
            return $options;
        });
    }

    // --- SupportsTemplateFetch ---

    public function fetchTemplates(): array
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return [];
        }

        try {
            $data = $this->postJsonOrFail(self::API_BASE . '/TokenList', ['ApiKey' => $apiKey]);
        } catch (\RuntimeException) {
            return [];
        }

        $rows = $data['Result'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $templates = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['Key'])) {
                continue;
            }

            $body = (string) ($row['TextTemplate'] ?? '');
            $templates[] = new ProviderTemplate(
                id: (string) $row['Key'],
                name: (string) ($row['Name'] ?? $row['Key']),
                language: 'fa',
                category: 'utility',
                status: $this->mapTemplateStatus($row['Status'] ?? null),
                bodyText: $body,
                variableCount: $this->countTemplateVariables($body),
            );
        }

        return $templates;
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
        $payload = [
            'endpoint'    => 'SendTokenSingle',
            'TemplateKey' => $mapping->providerTemplateId,
        ];

        foreach ($this->positionalParameters($resolvedVariables) as $position => $value) {
            $payload['p' . $position] = $value;
        }

        return $payload;
    }

    private function mapTemplateStatus(mixed $status): TemplateStatus
    {
        return match ((int) $status) {
            1 => TemplateStatus::Approved,
            2 => TemplateStatus::Pending,
            3 => TemplateStatus::Rejected,
            default => TemplateStatus::Disabled,
        };
    }

    private function countTemplateVariables(string $body): int
    {
        // Payamresan templates use {1}..{10} positional placeholders.
        $max = 0;
        if (preg_match_all('/\{(\d+)\}/', $body, $matches)) {
            foreach ($matches[1] as $idx) {
                $max = max($max, (int) $idx);
            }
        }
        return $max;
    }

    /**
     * Filter resolved variables down to positions 1..10 and return them in order.
     *
     * @return array<int, string>
     */
    private function positionalParameters(array $resolvedVariables): array
    {
        $params = [];
        foreach ($resolvedVariables as $position => $value) {
            $position = (int) $position;
            if ($position >= 1 && $position <= 10) {
                $params[$position] = (string) $value;
            }
        }
        ksort($params);
        return $params;
    }

    private function jsonHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }

    /**
     * POST helper that decodes JSON and throws on any error.
     *
     * Mirrors AbstractProvider::fetchJsonOrFail() but for POST endpoints.
     *
     * @throws \RuntimeException with a user-facing error message
     */
    private function postJsonOrFail(string $url, array $body): array
    {
        $result = $this->httpPost($url, [
            'headers' => $this->jsonHeaders(),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            throw new \RuntimeException(
                sprintf(__('Could not reach the %s API. Check your server\'s internet connection.', 'wp-sms'), $this->getName()),
            );
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            throw new \RuntimeException(__('Invalid credentials', 'wp-sms'));
        }

        if ($result['code'] === 429) {
            throw new \RuntimeException(__('Rate limited — please wait a moment and try again', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] < 200 || $result['code'] >= 300) {
            throw new \RuntimeException(
                $data['Error'] ?? sprintf('HTTP %d', $result['code']),
            );
        }

        if (!is_array($data)) {
            throw new \RuntimeException(
                sprintf(__('Invalid response from %s', 'wp-sms'), $this->getName()),
            );
        }

        if (!($data['Success'] ?? false)) {
            throw new \RuntimeException(
                $data['Error'] ?? __('Unknown error', 'wp-sms'),
            );
        }

        return $data;
    }
}

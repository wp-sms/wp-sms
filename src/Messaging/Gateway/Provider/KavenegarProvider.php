<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsTemplates;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Contracts\TestConnectionResult;

defined('ABSPATH') || exit;

class KavenegarProvider extends AbstractProvider implements SupportsTemplates
{
    private const API_BASE = 'https://api.kavenegar.com/v1';

    /** Max variables for Kavenegar verify/lookup endpoint */
    private const MAX_LOOKUP_TOKENS = 3;

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'kavenegar';
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
                    'description' => __('Found in your Kavenegar Panel under Settings > API Key', 'wp-sms'),
                    'placeholder' => 'Your Kavenegar API key',
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Your dedicated line number from the Kavenegar Panel > SMS > Lines', 'wp-sms'),
                        'placeholder' => '10001234567890',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');

        if (!$apiKey) {
            return DeliveryResult::failed(__('Kavenegar credentials not configured', 'wp-sms'));
        }

        $meta = $message->getMeta();

        // Flow direct template path — template already resolved
        if ($meta['template_mode'] ?? false) {
            return $this->sendLookup(
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
                return $this->sendLookup(
                    $apiKey,
                    $message->getRecipient(),
                    $mapping->providerTemplateId,
                    $resolved,
                );
            }
        }

        // Raw SMS send (no template)
        $sender = $this->getChannelConfig('sms', 'sender');
        if (!$sender) {
            return DeliveryResult::failed(__('Kavenegar sender not configured', 'wp-sms'));
        }

        $url = self::API_BASE . "/{$apiKey}/sms/send.json";

        $result = $this->httpPost($url, [
            'body' => [
                'receptor' => $message->getRecipient(),
                'sender'   => $sender,
                'message'  => $message->getBody(),
            ],
        ]);

        return $this->parseKavenegarResponse($result);
    }

    /**
     * Send via Kavenegar verify/lookup endpoint (template-based).
     */
    private function sendLookup(string $apiKey, string $receptor, string $templateName, array $resolvedVariables): DeliveryResult
    {
        $url = self::API_BASE . "/{$apiKey}/verify/lookup.json";

        $params = array_merge(
            ['receptor' => $receptor, 'template' => $templateName],
            $this->packTokenParams($resolvedVariables),
        );

        $result = $this->httpGet($url . '?' . http_build_query($params));

        return $this->parseKavenegarResponse($result);
    }

    private function parseKavenegarResponse(array|DeliveryResult $result): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if (($data['return']['status'] ?? 0) === 200) {
            $entry = $data['entries'][0] ?? [];
            return DeliveryResult::sent(
                providerId: (string) ($entry['messageid'] ?? ''),
                cost: isset($entry['cost']) ? (float) $entry['cost'] : null,
            );
        }

        return DeliveryResult::failed($data['return']['message'] ?? __('Kavenegar send failed', 'wp-sms'));
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $url = self::API_BASE . "/{$apiKey}/account/info.json";
        $result = $this->httpGet($url);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        return (string) ($data['entries']['remaincredit'] ?? '');
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');

        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $url = self::API_BASE . "/{$apiKey}/account/info.json";
        $result = $this->httpGet($url);

        $data = $this->validateTestResponse($result, 'Kavenegar');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $status = $data['return']['status'] ?? 0;
        if ($status !== 200) {
            $message = $data['return']['message'] ?? __('Unknown error', 'wp-sms');
            return TestConnectionResult::error($message);
        }

        $credit = (string) ($data['entries']['remaincredit'] ?? 'N/A');

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }

    // --- SupportsTemplates ---

    public function requiresTemplateForChannel(string $channel): bool
    {
        // Kavenegar supports both raw send and verify/lookup — templates are optional
        return false;
    }

    public function getVariableStyle(): VariableStyle
    {
        return VariableStyle::Positional;
    }

    public function buildTemplatePayload(TemplateMapping $mapping, array $resolvedVariables): array
    {
        return array_merge(
            ['endpoint' => 'verify/lookup', 'template' => $mapping->providerTemplateId],
            $this->packTokenParams($resolvedVariables),
        );
    }

    /** Map positional variables to Kavenegar's token, token2, token3 keys. */
    private function packTokenParams(array $resolvedVariables): array
    {
        $tokenKeys = ['token', 'token2', 'token3'];
        $params = [];

        foreach (array_values($resolvedVariables) as $i => $value) {
            if ($i >= self::MAX_LOOKUP_TOKENS) {
                break;
            }
            $params[$tokenKeys[$i]] = $value;
        }

        return $params;
    }
}

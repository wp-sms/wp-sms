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
 * FarazSMS — Iranian SMS gateway (brand: FarazSMS, panel: panel.iranpayamak.com).
 *
 * REST API endpoints (Api-Key header auth):
 *   Send raw:  POST https://api.iranpayamak.com/ws/v1/sms/simple
 *              { text, line_number, recipients: [...], number_format: "english" }
 *   Send pattern: POST https://api.iranpayamak.com/ws/v1/sms/pattern
 *              { code, attributes: {<name>: <value>}, recipient, line_number, number_format }
 *   Balance:   GET https://api.iranpayamak.com/ws/v1/account/balance
 *
 * Response shape: { status: "success"|"error", message?, data?: { balance_amount?, message_id?, ... } }
 *
 * Out of scope (not exposed by the API): MMS, flash SMS, voice/WhatsApp/RCS,
 * status callbacks (poll-only platform), inbound webhooks, opt-out detection.
 * Pattern path doubles as the OTP path, so a separate SupportsVerify implementation
 * is unnecessary.
 */
class FarazSmsProvider extends AbstractProvider implements SupportsTemplates
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.iranpayamak.com/ws/v1';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'farazsms';
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
                    'description' => __('Found in your FarazSMS panel (panel.iranpayamak.com) under the API/Webservice section', 'wp-sms'),
                    'placeholder' => 'Your FarazSMS API key',
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Line Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Your dedicated line number purchased from the FarazSMS panel', 'wp-sms'),
                        'placeholder' => '50001234567890',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');

        if ($apiKey === '') {
            return DeliveryResult::failed(__('FarazSMS credentials not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');
        $meta   = $message->getMeta();

        // Flow direct template path — provider template ID supplied in meta.
        if ($meta['template_mode'] ?? false) {
            return $this->sendPattern(
                $apiKey,
                $sender,
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
                return $this->sendPattern(
                    $apiKey,
                    $sender,
                    $message->getRecipient(),
                    $mapping->providerTemplateId,
                    $resolved,
                );
            }
        }

        if ($sender === '') {
            return DeliveryResult::failed(__('FarazSMS sender not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/sms/simple', [
            'headers' => $this->apiHeaders($apiKey),
            'body'    => wp_json_encode([
                'text'          => $message->getBody(),
                'line_number'   => $sender,
                'recipients'    => [$message->getRecipient()],
                'number_format' => 'english',
            ]),
        ]);

        return $this->parseResponse($result);
    }

    private function sendPattern(string $apiKey, string $sender, string $recipient, string $code, array $resolvedVariables): DeliveryResult
    {
        $result = $this->httpPost(self::API_BASE . '/sms/pattern', [
            'headers' => $this->apiHeaders($apiKey),
            'body'    => wp_json_encode([
                'code'          => $code,
                'attributes'    => (object) $resolvedVariables,
                'recipient'     => $recipient,
                'line_number'   => $sender,
                'number_format' => 'english',
            ]),
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
            return DeliveryResult::failed(__('Invalid response from FarazSMS', 'wp-sms'));
        }

        if (($data['status'] ?? '') === 'success') {
            $messageId = $data['data']['message_id'] ?? null;
            return DeliveryResult::sent($messageId !== null ? (string) $messageId : null);
        }

        return DeliveryResult::failed((string) ($data['message'] ?? __('FarazSMS send failed', 'wp-sms')));
    }

    public function getCredit(): ?string
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/account/balance', [
            'headers' => $this->apiHeaders($apiKey),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
            return null;
        }

        return isset($data['data']['balance_amount']) ? (string) $data['data']['balance_amount'] : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');

        if ($apiKey === '') {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/account/balance', [
            'headers' => $this->apiHeaders($apiKey),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'FarazSMS');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (($data['status'] ?? '') !== 'success') {
            return TestConnectionResult::error((string) ($data['message'] ?? __('Unknown error', 'wp-sms')));
        }

        $credit = isset($data['data']['balance_amount']) ? (string) $data['data']['balance_amount'] : 'N/A';

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
            'endpoint'   => 'pattern',
            'code'       => $mapping->providerTemplateId,
            'attributes' => $resolvedVariables,
        ];
    }

    private function apiHeaders(string $apiKey): array
    {
        return [
            'Api-Key'      => $apiKey,
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }
}

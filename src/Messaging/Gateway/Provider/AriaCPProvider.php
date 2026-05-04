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
 * Aria CP (formerly PayamakAria) — Iranian SMS panel running on the Payamak Panel platform.
 *
 * The brand rebranded from PayamakAria to Aria CP; both payamakaria.ir and
 * payamakaria.com 301-redirect to https://pozariacp.com/, with the customer
 * panel at https://panel2.pozariacp.com/. Slug stays 'payamakaria' for
 * continuity with v1 user installs.
 *
 * v1 used the legacy SOAP endpoint at api.payamak-panel.com/post/send.asmx;
 * v2 uses the REST API at rest.payamak-panel.com/api/SendSMS — same backend,
 * same credentials, same response shape as the sister Razpayamak and
 * Farapayamak providers (all on the shared Payamak Panel platform):
 *   Send:    POST https://rest.payamak-panel.com/api/SendSMS/SendSMS
 *            { username, password, to, from, text, isFlash }
 *   Pattern: POST https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber
 *            { username, password, to, bodyId, text }   (text = ";"-joined vars)
 *   Credit:  POST https://rest.payamak-panel.com/api/SendSMS/GetCredit
 *            { username, password }
 *
 * Auth: account username + password sent in every request body.
 * Response shape: { Value, RetStatus, StrRetStatus } — RetStatus == 1 is success.
 *
 * Out of scope (not exposed by the API): MMS, flash SMS, status webhooks
 * (delivery is poll-only via GetDeliveries2), inbound webhooks (poll-only via
 * GetMessages), and opt-out detection. Pattern path doubles as the OTP path,
 * so a separate SupportsVerify implementation is unnecessary.
 */
class AriaCPProvider extends AbstractProvider implements SupportsTemplates
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://rest.payamak-panel.com/api/SendSMS';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'payamakaria';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'username' => [
                    'type'        => 'string',
                    'label'       => __('API Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Aria CP panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Aria CP panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the Aria CP panel', 'wp-sms'),
                        'placeholder' => '5000xxxxxxxx',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('Aria CP credentials not configured', 'wp-sms'));
        }

        $meta = $message->getMeta();

        // Flow direct template path — provider template ID supplied in meta.
        if ($meta['template_mode'] ?? false) {
            return $this->sendPattern(
                $username,
                $password,
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
                    $username,
                    $password,
                    $message->getRecipient(),
                    $mapping->providerTemplateId,
                    $resolved,
                );
            }
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('Aria CP sender not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/SendSMS', [
            'body' => [
                'username' => $username,
                'password' => $password,
                'to'       => $message->getRecipient(),
                'from'     => $sender,
                'text'     => $message->getBody(),
                'isFlash'  => 'false',
            ],
        ]);

        return $this->parseResponse($result);
    }

    private function sendPattern(string $username, string $password, string $recipient, string $bodyId, array $resolvedVariables): DeliveryResult
    {
        $result = $this->httpPost(self::API_BASE . '/BaseServiceNumber', [
            'body' => [
                'username' => $username,
                'password' => $password,
                'to'       => $recipient,
                'bodyId'   => $bodyId,
                'text'     => implode(';', array_values($resolvedVariables)),
            ],
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
            return DeliveryResult::failed(__('Invalid response from Aria CP', 'wp-sms'));
        }

        if ((int) ($data['RetStatus'] ?? 0) === 1) {
            return DeliveryResult::sent((string) ($data['Value'] ?? ''));
        }

        return DeliveryResult::failed((string) ($data['StrRetStatus'] ?? __('Aria CP send failed', 'wp-sms')));
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        if ($username === '' || $password === '') {
            return null;
        }

        $result = $this->httpPost(self::API_BASE . '/GetCredit', [
            'body' => ['username' => $username, 'password' => $password],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || (int) ($data['RetStatus'] ?? 0) !== 1) {
            return null;
        }

        return (string) ($data['Value'] ?? '');
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/GetCredit', [
            'body' => ['username' => $username, 'password' => $password],
        ]);

        $data = $this->validateTestResponse($result, 'Aria CP');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if ((int) ($data['RetStatus'] ?? 0) !== 1) {
            $message = (string) ($data['StrRetStatus'] ?? __('Unknown error', 'wp-sms'));
            return TestConnectionResult::error($message);
        }

        $credit = (string) ($data['Value'] ?? 'N/A');
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
        return VariableStyle::Positional;
    }

    public function buildTemplatePayload(TemplateMapping $mapping, array $resolvedVariables): array
    {
        return [
            'endpoint' => 'BaseServiceNumber',
            'bodyId'   => $mapping->providerTemplateId,
            'text'     => implode(';', array_values($resolvedVariables)),
        ];
    }
}

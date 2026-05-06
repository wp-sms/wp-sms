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
 * Melipayamak — Iranian SMS panel and the canonical Payamak Panel platform.
 *
 * Uses the modern REST API at rest.payamak-panel.com. Differs from sister
 * Payamak-Panel resellers in that the standard send path uses the SmartSMS
 * endpoint (which supports optional backup sender numbers for failover),
 * while the pattern path is the same /SendSMS/BaseServiceNumber call:
 *   Send:    POST https://rest.payamak-panel.com/api/SmartSMS/Send
 *            { username, password, to, from, text, fromSupportOne?, fromSupportTwo? }
 *   Pattern: POST https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber
 *            { username, password, to, bodyId, text }   (text = ";"-joined vars)
 *   Credit:  POST https://rest.payamak-panel.com/api/SendSMS/GetCredit
 *            { username, password }
 *
 * Auth: account username + password sent in every request body.
 * Response shape: { Value, RetStatus, StrRetStatus } — RetStatus == 1 is success.
 *
 * Out of scope (not exposed by the API or dropped on the v7→v8 port): MMS,
 * flash SMS, batched per-recipient template fan-out, the v7 "Value-as-error-code"
 * lookup table (provider error text comes back as StrRetStatus), and status
 * webhooks (delivery is poll-only via GetDeliveries2).
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class MeliPayamakProvider extends AbstractProvider implements SupportsTemplates
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://rest.payamak-panel.com/api';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'melipayamak';
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
                    'description' => __('Your Melipayamak panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Melipayamak panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the Melipayamak panel', 'wp-sms'),
                        'placeholder' => '5000xxxxxxxx',
                    ],
                    'from_support_one' => [
                        'type'        => 'string',
                        'label'       => __('Backup sender 1 (optional)', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Optional backup sender used by SmartSMS for failover', 'wp-sms'),
                    ],
                    'from_support_two' => [
                        'type'        => 'string',
                        'label'       => __('Backup sender 2 (optional)', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Optional secondary backup sender used by SmartSMS for failover', 'wp-sms'),
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
            return DeliveryResult::failed(__('Melipayamak credentials not configured', 'wp-sms'));
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
            return DeliveryResult::failed(__('Melipayamak sender not configured', 'wp-sms'));
        }

        $body = [
            'username' => $username,
            'password' => $password,
            'to'       => $message->getRecipient(),
            'from'     => $sender,
            'text'     => $message->getBody(),
        ];

        $supportOne = (string) $this->getChannelConfig('sms', 'from_support_one', '');
        if ($supportOne !== '') {
            $body['fromSupportOne'] = $supportOne;
        }

        $supportTwo = (string) $this->getChannelConfig('sms', 'from_support_two', '');
        if ($supportTwo !== '') {
            $body['fromSupportTwo'] = $supportTwo;
        }

        $result = $this->httpPost(self::API_BASE . '/SmartSMS/Send', [
            'body' => $body,
        ]);

        return $this->parseResponse($result);
    }

    private function sendPattern(string $username, string $password, string $recipient, string $bodyId, array $resolvedVariables): DeliveryResult
    {
        $result = $this->httpPost(self::API_BASE . '/SendSMS/BaseServiceNumber', [
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
            return DeliveryResult::failed(__('Invalid response from Melipayamak', 'wp-sms'));
        }

        if ((int) ($data['RetStatus'] ?? 0) === 1) {
            return DeliveryResult::sent((string) ($data['Value'] ?? ''));
        }

        return DeliveryResult::failed((string) ($data['StrRetStatus'] ?? __('Melipayamak send failed', 'wp-sms')));
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        if ($username === '' || $password === '') {
            return null;
        }

        $result = $this->httpPost(self::API_BASE . '/SendSMS/GetCredit', [
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

        $result = $this->httpPost(self::API_BASE . '/SendSMS/GetCredit', [
            'body' => ['username' => $username, 'password' => $password],
        ]);

        $data = $this->validateTestResponse($result, 'Melipayamak');
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

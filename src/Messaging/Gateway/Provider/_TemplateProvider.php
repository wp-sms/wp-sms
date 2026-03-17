<?php

/**
 * Template Provider — Reference file for writing new SMS/messaging providers.
 *
 * This file is NOT registered in the service provider. It exists as documentation
 * to demonstrate every method and config option available to providers.
 *
 * To create a new provider:
 * 1. Copy this file and rename it (e.g., AcmeProvider.php)
 * 2. Update the namespace, class name, and implement all abstract methods
 * 3. Add the provider to MessagingServiceProvider::PROVIDERS
 *
 * @see \WSms\Messaging\Gateway\AbstractProvider
 */

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

class _TemplateProvider extends AbstractProvider
{
    /**
     * Unique identifier — used as the key in wsms_gateway_configs and the REST API.
     * Use lowercase, alphanumeric + underscores. Must be unique across all providers.
     */
    public function getId(): string
    {
        return 'template';
    }

    /**
     * Human-readable name shown in the admin UI.
     */
    public function getName(): string
    {
        return 'Template Provider';
    }

    /**
     * Channels this provider can send through.
     * Common values: 'sms', 'whatsapp', 'telegram', 'viber'
     *
     * Multi-channel example: return ['sms', 'whatsapp'];
     */
    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    /**
     * Config schema with nested structure.
     *
     * - `shared`: credentials and settings used across all channels
     * - `channels`: per-channel fields (e.g., different sender IDs for SMS vs WhatsApp)
     *
     * Field types:
     * - 'string'  — text input
     * - 'secret'  — password input (masked in UI, encrypted at rest)
     * - 'select'  — dropdown, requires 'options' array of ['value' => '...', 'label' => '...']
     * - 'boolean' — checkbox/toggle
     * - 'number'  — numeric input
     */
    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your API key from the provider dashboard', 'wp-sms'),
                ],
                'api_secret' => [
                    'type'        => 'secret',
                    'label'       => __('API Secret', 'wp-sms'),
                    'required'    => true,
                ],
                'use_sandbox' => [
                    'type'    => 'boolean',
                    'label'   => __('Sandbox Mode', 'wp-sms'),
                    'default' => false,
                ],
            ],
            'channels' => [
                'sms' => [
                    'from_number' => [
                        'type'     => 'string',
                        'label'    => __('Sender Number', 'wp-sms'),
                        'required' => true,
                    ],
                ],
                // Multi-channel example:
                // 'whatsapp' => [
                //     'from_number' => [
                //         'type'     => 'string',
                //         'label'    => __('WhatsApp Number', 'wp-sms'),
                //         'required' => true,
                //     ],
                // ],
            ],
        ];
    }

    /**
     * Provider metadata for the admin UI.
     *
     * Keys:
     * - description: One-line description
     * - website: Provider's website URL
     * - icon: URL or path to provider icon (48x48 recommended)
     * - regions: Array of country/region codes where this provider operates
     */
    public function getMetadata(): array
    {
        return [
            'description' => __('Example provider for reference', 'wp-sms'),
            'website'     => 'https://example.com',
            'icon'        => '',
            'regions'     => ['global'],
        ];
    }

    /**
     * Feature flags — which capabilities this provider supports.
     *
     * Override only the features that differ from the defaults in AbstractProvider.
     */
    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'bulk_send'        => true,
            'delivery_receipt' => true,
            'scheduled_send'   => true,
        ]);
    }

    /**
     * Send a message through this provider's API.
     *
     * Access config via:
     * - $this->getSharedConfig('api_key')
     * - $this->getChannelConfig('sms', 'from_number')
     *
     * Use HTTP helpers:
     * - $this->httpPost($url, $args)
     * - $this->httpGet($url, $args)
     *
     * Both return ['response' => ..., 'body' => ..., 'code' => ...] on success,
     * or DeliveryResult::failed() on WP_Error.
     *
     * @return DeliveryResult Use DeliveryResult::sent(), ::failed(), or ::queued()
     */
    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        $channel = $message->getChannel();

        // Route by channel for multi-channel providers
        $from = $this->getChannelConfig($channel, 'from_number');

        $result = $this->httpPost('https://api.example.com/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'from'    => $from,
                'to'      => $message->getRecipient(),
                'message' => $message->getBody(),
                'channel' => $channel,
            ]),
        ]);

        // httpPost returns DeliveryResult on WP_Error
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] !== 200) {
            $error = json_decode($result['body'], true);
            return DeliveryResult::failed($error['message'] ?? "HTTP {$result['code']}");
        }

        $data = json_decode($result['body'], true);

        return DeliveryResult::sent(
            providerId: $data['message_id'] ?? null,
            cost: isset($data['cost']) ? (float) $data['cost'] : null,
        );
    }

    /**
     * Fetch account credit/balance from the provider API.
     * Return null if the provider doesn't support balance queries.
     */
    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpGet('https://api.example.com/balance', [
            'headers' => ['Authorization' => 'Bearer ' . $apiKey],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        return $data['balance'] ?? null;
    }
}

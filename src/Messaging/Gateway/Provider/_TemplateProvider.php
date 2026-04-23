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
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

/**
 * Optional interfaces:
 * - SupportsStatusCallback: Implement to receive delivery status webhooks from the provider.
 *   The platform routes callbacks to POST|GET /wsms/v1/callbacks/{gateway_id}/status
 *   and calls your validateStatusCallback() and parseStatusCallback() methods.
 *
 * MMS / Media:
 * - Media URLs are passed via $message->getMeta()['media_urls'] (array of URLs).
 *   Read this in doSend() and include in the provider's API request as needed.
 */
class _TemplateProvider extends AbstractProvider implements SupportsStatusCallback
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
     *
     * Optional field properties:
     * - 'placeholder' — example value shown in the input when empty
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
                    'description' => __('Your API secret from the provider dashboard', 'wp-sms'),
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
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Your sender phone number in E.164 format', 'wp-sms'),
                        'placeholder' => '+15551234567',
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
     * - setup_url: Direct link to the provider's API credentials/dashboard page
     * - setup_notes: Array of step-by-step setup instructions shown in the admin UI
     */
    public function getMetadata(): array
    {
        return [
            'description' => __('Example provider for reference', 'wp-sms'),
            'website'     => 'https://example.com',
            'icon'        => '',
            'regions'     => ['global'],
            'setup_url'   => 'https://example.com/dashboard',
            'setup_notes' => [
                __('Find your API Key on the provider dashboard under Settings > API.', 'wp-sms'),
            ],
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
            'delivery_receipt' => true,
            'test_connection'  => true, // Enable when your provider implements testConnection()
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

        $body = [
            'from'    => $from,
            'to'      => $message->getRecipient(),
            'message' => $message->getBody(),
            'channel' => $channel,
        ];

        // Status callback — tell the provider where to POST delivery updates.
        // Only needed if implementing SupportsStatusCallback.
        $body['callback_url'] = $this->getStatusCallbackUrl();

        // MMS / Media — attach media URLs from message meta.
        // The flow builder's "Media URL" field populates this automatically.
        $mediaUrls = $message->getMeta()['media_urls'] ?? [];
        if (!empty($mediaUrls)) {
            $body['media'] = $mediaUrls;
        }

        $result = $this->httpPost('https://api.example.com/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
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

    // ──────────────────────────────────────────────────────────────────────
    // SupportsStatusCallback — Delivery status webhooks (optional interface)
    //
    // Implement these three methods if the provider sends delivery reports
    // via webhook. The platform handles routing, rate limiting, and log updates;
    // the provider only handles authentication and payload parsing.
    //
    // Flow: Provider POSTs to /wsms/v1/callbacks/{gateway_id}/status
    //   → GatewayCallbackController resolves provider
    //   → validateStatusCallback() — verify authenticity (signature, token, etc.)
    //   → parseStatusCallback()   — parse into StatusUpdate[] (array for batch support)
    //   → Platform updates message log entries automatically
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Validate the incoming callback request is authentic.
     *
     * Common patterns:
     * - HMAC signature header (Twilio: X-Twilio-Signature, Plivo: X-Plivo-Signature-V3)
     * - Shared secret / bearer token in a header
     * - IP allowlisting (use with caution)
     *
     * Return false → platform responds 403 and stops processing.
     */
    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        $apiSecret = $this->getSharedConfig('api_secret');
        if (!$apiSecret) {
            return false;
        }

        $signature = $request->get_header('x-provider-signature');
        if (empty($signature)) {
            return false;
        }

        // Example: HMAC-SHA256 of request body
        $body = $request->get_body() ?? '';
        $expected = hash_hmac('sha256', $body, $apiSecret);

        return hash_equals($expected, $signature);
    }

    /**
     * Parse the provider's webhook payload into normalized StatusUpdate(s).
     *
     * Return an array — most providers send one update per callback, but some
     * (e.g., Infobip) batch multiple statuses in a single request.
     *
     * Normalize the provider's status to one of: 'queued', 'sent', 'delivered', 'failed'
     *
     * Set `permanent: true` on StatusUpdate when the error code indicates a permanent
     * delivery failure (invalid number, deactivated handset, etc.). This triggers
     * automatic contact status update to 'bounced' or 'complained'.
     * Set `complaint: true` when the error is a spam/unsubscribe complaint.
     * See TwilioProvider::isPermanentTwilioError() for a reference implementation.
     *
     * Return [] to silently ignore the callback (e.g., irrelevant event type).
     *
     * @return StatusUpdate[]
     */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        // JSON body example
        $data = $request->get_json_params();
        $messageId = $data['message_id'] ?? null;
        $providerStatus = $data['status'] ?? null;

        if (empty($messageId) || empty($providerStatus)) {
            return [];
        }

        // Map provider-specific statuses to normalized values
        $status = match ($providerStatus) {
            'queued', 'accepted', 'buffered' => 'queued',
            'sending', 'sent', 'submitted'   => 'sent',
            'delivered', 'read'              => 'delivered',
            'rejected', 'failed', 'expired'  => 'failed',
            default                          => $providerStatus,
        };

        return [new StatusUpdate(
            providerId: $messageId,
            status: $status,
            errorCode: $data['error_code'] ?? null,
            errorMessage: $data['error_message'] ?? null,
        )];
    }

    /**
     * The URL to pass to the provider API when sending, so it knows where to
     * POST delivery updates. Called automatically — just return the standard URL.
     */
    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
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

    /**
     * Test the gateway connection without sending a message.
     * Typically calls a lightweight read-only API endpoint (e.g., account info, balance).
     * Reuse the same HTTP call as getCredit() but with proper error handling.
     *
     * Use $this->validateTestResponse() to handle common error patterns
     * (network errors, rate-limiting, non-2xx, malformed JSON).
     * Check provider-specific HTTP codes (401, 403, 404) before calling it.
     */
    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is not configured', 'wp-sms'));
        }

        $result = $this->httpGet('https://api.example.com/account', [
            'headers' => ['Authorization' => 'Bearer ' . $apiKey],
        ]);

        // Check provider-specific error codes before common validation
        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid API credentials', 'wp-sms'));
            }
        }

        // Handles: network errors, 429, generic non-2xx, malformed JSON
        $data = $this->validateTestResponse($result, 'Example Provider');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        return TestConnectionResult::ok(__('Connection successful', 'wp-sms'), [
            'balance' => $data['balance'] ?? 'N/A',
        ]);
    }
}

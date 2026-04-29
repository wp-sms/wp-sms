<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsInboundMessage;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

class OctopushProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = true;

    private const API_BASE = 'https://api.octopush.com/v1/public';

    public function getId(): string
    {
        return 'octopush';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_login' => [
                    'type'        => 'string',
                    'label'       => __('API Login (Email)', 'wp-sms'),
                    'required'    => true,
                    'description' => __('The email address of your Octopush account, used as the api-login header.', 'wp-sms'),
                    'placeholder' => 'you@example.com',
                ],
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Octopush API Key from the dashboard under API & Integrations.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('3–11 alphanumeric characters. French/EU regulatory rules apply.', 'wp-sms'),
                    ],
                    'type' => [
                        'type'        => 'select',
                        'label'       => __('SMS Type', 'wp-sms'),
                        'required'    => false,
                        'default'     => 'sms_low_cost',
                        'description' => __('Low-cost is suitable for transactional traffic. Premium uses higher-quality routes and supports two-way SMS, but messages must include a STOP clause per French regulation. Defaults to low-cost.', 'wp-sms'),
                        'options'     => [
                            ['value' => 'sms_low_cost', 'label' => __('Low cost', 'wp-sms')],
                            ['value' => 'sms_premium',  'label' => __('Premium', 'wp-sms')],
                        ],
                    ],
                    'purpose' => [
                        'type'        => 'select',
                        'label'       => __('Premium Purpose', 'wp-sms'),
                        'required'    => false,
                        'placeholder' => __('— Not set —', 'wp-sms'),
                        'description' => __('Optional. Used for premium SMS: alert for transactional messages, wholesale for marketing.', 'wp-sms'),
                        'options'     => [
                            ['value' => 'alert',     'label' => __('Alert (transactional)', 'wp-sms')],
                            ['value' => 'wholesale', 'label' => __('Wholesale (marketing)', 'wp-sms')],
                        ],
                    ],
                    'default_country' => [
                        'type'        => 'select',
                        'label'       => __('Default Country', 'wp-sms'),
                        'required'    => false,
                        'default'     => 'FR',
                        'description' => __('ISO country code used when checking your Octopush wallet balance and testing the connection. Defaults to France.', 'wp-sms'),
                        'options'     => [
                            ['value' => 'FR', 'label' => __('France', 'wp-sms')],
                            ['value' => 'BE', 'label' => __('Belgium', 'wp-sms')],
                            ['value' => 'CH', 'label' => __('Switzerland', 'wp-sms')],
                            ['value' => 'LU', 'label' => __('Luxembourg', 'wp-sms')],
                            ['value' => 'GB', 'label' => __('United Kingdom', 'wp-sms')],
                            ['value' => 'US', 'label' => __('United States', 'wp-sms')],
                            ['value' => 'DE', 'label' => __('Germany', 'wp-sms')],
                            ['value' => 'ES', 'label' => __('Spain', 'wp-sms')],
                            ['value' => 'IT', 'label' => __('Italy', 'wp-sms')],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiLogin = $this->getSharedConfig('api_login');
        $apiKey   = $this->getSharedConfig('api_key');
        if (!$apiLogin || !$apiKey) {
            return DeliveryResult::failed(__('Octopush credentials not configured', 'wp-sms'));
        }

        $sender = $this->getChannelConfig('sms', 'from');
        if (!$sender) {
            return DeliveryResult::failed(__('Octopush Sender ID not configured', 'wp-sms'));
        }

        $type = $this->getChannelConfig('sms', 'type', 'sms_low_cost');

        $body = [
            'recipients' => [['phone_number' => $message->getRecipient()]],
            'text'       => $message->getBody(),
            'sender'     => $sender,
            'type'       => $type,
        ];

        $purpose = $this->getChannelConfig('sms', 'purpose');
        if ($purpose) {
            $body['purpose'] = $purpose;
        }

        // TODO: idempotency once the dispatcher exposes a stable per-attempt ID.

        $result = $this->httpPost(self::API_BASE . '/sms-campaign/send', [
            'headers' => $this->authHeaders($apiLogin, $apiKey),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Octopush API Login or API Key', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300 && is_array($data) && !empty($data['sms_ticket'])) {
            return DeliveryResult::sent(
                providerId: (string) $data['sms_ticket'],
                cost: isset($data['total_cost']) ? (float) $data['total_cost'] : null,
                meta: array_filter([
                    'residual_credit' => $data['residual_credit'] ?? null,
                    'number_of_sms'   => $data['number_of_sms_needed'] ?? null,
                ], fn($v) => $v !== null),
            );
        }

        $errorMessage = is_array($data) ? ($data['message'] ?? $data['detail'] ?? null) : null;

        return DeliveryResult::failed(
            $errorMessage ?: sprintf(__('Octopush rejected the request (HTTP %d)', 'wp-sms'), $result['code']),
            meta: array_filter([
                'octopush_error_code' => is_array($data) ? ($data['code'] ?? null) : null,
                'http_code'           => $result['code'],
            ], fn($v) => $v !== null),
        );
    }

    public function getCredit(): ?string
    {
        $apiLogin = $this->getSharedConfig('api_login');
        $apiKey   = $this->getSharedConfig('api_key');
        if (!$apiLogin || !$apiKey) {
            return null;
        }

        $result = $this->httpGet($this->balanceUrl(), [
            'headers' => $this->authHeaders($apiLogin, $apiKey),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['amount'])) {
            return null;
        }

        $unit = isset($data['unit']) ? ' ' . $data['unit'] : '';
        return ((string) $data['amount']) . $unit;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiLogin = $this->getSharedConfig('api_login');
        $apiKey   = $this->getSharedConfig('api_key');

        if (!$apiLogin || !$apiKey) {
            return TestConnectionResult::error(__('API Login and API Key are required', 'wp-sms'));
        }

        $result = $this->httpGet($this->balanceUrl(), [
            'headers' => $this->authHeaders($apiLogin, $apiKey),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Octopush API Login or API Key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Octopush');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $amount = $data['amount'] ?? 'N/A';
        $unit   = $data['unit']   ?? '';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s %s', 'wp-sms'), $amount, $unit),
            ['balance' => (string) $amount],
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status', ['token' => $this->callbackToken()]);
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $payload   = $this->extractPayload($request);
        $messageId = $payload['message_id'] ?? null;
        $rawStatus = $payload['status']     ?? null;

        if (empty($messageId) || empty($rawStatus)) {
            return [];
        }

        $status = match ($rawStatus) {
            'DELIVERED'                       => 'delivered',
            'ACK', 'UNKNOWN_DELIVERY'         => 'sent',
            'NOT_DELIVERED', 'BAD_DESTINATION', 'BLACKLISTED_NUMBER',
            'NOT_ALLOWED', 'UNDEFINED'        => 'failed',
            default                           => $rawStatus,
        };

        $permanent = in_array($rawStatus, [
            'BAD_DESTINATION',
            'BLACKLISTED_NUMBER',
            'NOT_ALLOWED',
        ], true);

        $unsubscribe = $rawStatus === 'BLACKLISTED_NUMBER';

        return [new StatusUpdate(
            providerId:   (string) $messageId,
            status:       $status,
            errorCode:    is_string($rawStatus) ? $rawStatus : null,
            errorMessage: $status === 'failed'
                ? sprintf(__('Octopush DLR: %s', 'wp-sms'), $rawStatus)
                : null,
            permanent:    $permanent,
            unsubscribe:  $unsubscribe,
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound', ['token' => $this->callbackToken()]);
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $payload = $this->extractPayload($request);
        $from    = $payload['number']          ?? null;
        $to      = $payload['sim_card_number'] ?? null;
        $body    = $payload['text']            ?? null;

        if (empty($from) || $body === null) {
            return [];
        }

        return [new InboundMessage(
            from:       (string) $from,
            to:         (string) ($to ?? ''),
            body:       (string) $body,
            providerId: isset($payload['message_id']) ? (string) $payload['message_id'] : null,
            meta:       array_filter([
                'reception_date' => $payload['reception_date'] ?? null,
            ]),
        )];
    }

    // --- Internal ---

    private function authHeaders(string $apiLogin, string $apiKey): array
    {
        return [
            'api-login'    => $apiLogin,
            'api-key'      => $apiKey,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }

    private function balanceUrl(): string
    {
        $product = $this->getChannelConfig('sms', 'type', 'sms_low_cost');
        $country = $this->getChannelConfig('sms', 'default_country', 'FR');

        return self::API_BASE . '/wallet/check-balance?'
            . http_build_query(['product_name' => $product, 'country_code' => $country], '', '&', PHP_QUERY_RFC3986);
    }

    private function callbackToken(): string
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return '';
        }
        return hash_hmac('sha256', 'octopush-callback', $apiKey);
    }

    private function verifyToken(\WP_REST_Request $request): bool
    {
        $expected = $this->callbackToken();
        if ($expected === '') {
            return false;
        }
        $given = (string) ($request->get_param('token') ?? '');
        if ($given === '') {
            return false;
        }
        return hash_equals($expected, $given);
    }

    /**
     * Read the JSON body Octopush POSTs to webhook URLs.
     *
     * Falls back to query/form params so tests using set_param() work without
     * having to construct a JSON request body.
     */
    private function extractPayload(\WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        if (is_array($json) && !empty($json)) {
            return $json;
        }

        $params = $request->get_params();
        unset($params['token'], $params['gateway_id']);
        return $params;
    }
}

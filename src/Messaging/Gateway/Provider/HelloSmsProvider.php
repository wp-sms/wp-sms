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

/**
 * HelloSMS (hellosms.se) — Swedish SMS provider.
 *
 * Auth: HTTP Basic with API username + password from dashboard.hellosms.se.
 * Webhooks (DLR + inbound) are not signed; this provider authenticates them
 * via a shared `callback_token` appended as `?token=…` to the registered URL,
 * matching the SmsApiProvider pattern. The DLR + inbound URLs themselves are
 * registered account-wide via support@hellosms.se. SIGNOFF webhook events
 * surface as InboundMessage with optOutType=STOP so OptOutManager handles
 * them through the existing keyword-matcher path.
 */
class HelloSmsProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.hellosms.se/api/v1';

    public function getId(): string
    {
        return 'hellosms';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_username' => [
                    'type'        => 'string',
                    'label'       => __('API Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('API username from Dashboard → API at dashboard.hellosms.se.', 'wp-sms'),
                ],
                'api_password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('API password generated alongside the API username.', 'wp-sms'),
                ],
                'callback_token' => [
                    'type'        => 'string',
                    'label'       => __('Callback Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Shared secret appended to the DLR/inbound URLs as ?token=… so the receiver can authenticate webhooks. HelloSMS does not sign callbacks.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender Name', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Approved sender name: 3-11 characters, A-Z, 0-9, space, underscore, å/ä/ö. Leave blank to use your account default.', 'wp-sms'),
                        'placeholder' => 'MyBrand',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = $this->getSharedConfig('api_username');
        $password = $this->getSharedConfig('api_password');

        if (!$username || !$password) {
            return DeliveryResult::failed(__('HelloSMS credentials not configured', 'wp-sms'));
        }

        $body = [
            'to'      => $message->getRecipient(),
            'message' => $message->getBody(),
        ];

        $from = $this->getChannelConfig('sms', 'from');
        if ($from) {
            $body['from'] = $from;
        }

        if ($this->getSharedConfig('callback_token')) {
            $body['sendApiCallback'] = true;
        }

        $result = $this->httpPost(self::API_BASE . '/sms/send', [
            'headers' => $this->authHeaders($username, $password) + [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid HelloSMS credentials', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300 && is_array($data) && ($data['status'] ?? null) === 'success') {
            $entry = $data['messageIds'][0] ?? null;
            $providerId = is_array($entry) ? ($entry['apiMessageId'] ?? null) : null;

            // HelloSMS reports per-recipient status: 0 = queued, -5 = country not enabled.
            $perRecipient = is_array($entry) ? ($entry['status'] ?? 0) : 0;
            if ((int) $perRecipient === -5) {
                return DeliveryResult::failed(
                    __('HelloSMS rejected the message: destination country is not enabled on this account', 'wp-sms'),
                    meta: array_filter([
                        'hellosms_status'  => '-5',
                        'hellosms_message' => is_array($entry) ? ($entry['message'] ?? null) : null,
                    ]),
                );
            }

            return DeliveryResult::queued($providerId !== null ? (string) $providerId : null);
        }

        $errorText = is_array($data) ? ($data['statusText'] ?? $data['message'] ?? null) : null;

        return DeliveryResult::failed(
            $errorText ?: sprintf('HTTP %d', $result['code']),
            meta: array_filter([
                'hellosms_code' => $result['code'] ?: null,
            ]),
        );
    }

    public function getCredit(): ?string
    {
        $username = $this->getSharedConfig('api_username');
        $password = $this->getSharedConfig('api_password');

        if (!$username || !$password) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/account/balance', [
            'headers' => $this->authHeaders($username, $password),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return null;
        }

        // The docs don't expose the balance field name; v7 uses `credits`.
        // Try the most likely keys before giving up.
        $value = $data['credits'] ?? $data['balance'] ?? $data['amount'] ?? null;
        if ($value === null) {
            return null;
        }

        return number_format((float) $value, 2) . ' credits';
    }

    public function testConnection(): TestConnectionResult
    {
        $username = $this->getSharedConfig('api_username');
        $password = $this->getSharedConfig('api_password');

        if (!$username || !$password) {
            return TestConnectionResult::error(__('API Username and API Password are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/test', [
            'headers' => $this->authHeaders($username, $password),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid API Username or API Password', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'HelloSMS');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        return TestConnectionResult::ok(__('Connected to HelloSMS', 'wp-sms'));
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->validateCallbackToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $providerId = $request->get_param('apiMessageId');
        $rawStatus = (string) ($request->get_param('status') ?? '');

        if (empty($providerId) || $rawStatus === '') {
            return [];
        }

        $normalized = match (strtolower($rawStatus)) {
            'delivered'                  => 'delivered',
            'failed', 'not delivered'    => 'failed',
            default                      => $rawStatus,
        };

        return [new StatusUpdate(
            providerId:   (string) $providerId,
            status:       $normalized,
            errorCode:    $normalized === 'failed' ? $rawStatus : null,
            errorMessage: $normalized === 'failed' ? sprintf('HelloSMS: %s', $rawStatus) : null,
            permanent:    $normalized === 'failed',
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->validateCallbackToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $action = (string) ($request->get_param('action') ?? '');
        $from = (string) ($request->get_param('from') ?? '');

        if ($from === '') {
            return [];
        }

        $messageId = $request->get_param('message_id');
        $providerId = $messageId !== null ? (string) $messageId : null;
        $to = (string) ($request->get_param('to') ?? '');
        $text = (string) ($request->get_param('text') ?? '');
        $meta = array_filter([
            'timestamp' => $request->get_param('timestamp'),
            'action'    => $action ?: null,
        ]);

        if (strtoupper($action) === 'SIGNOFF') {
            // SIGNOFF is HelloSMS's opt-out event; map to a synthetic STOP keyword
            // so OptOutManager + KeywordMatcher take over.
            return [new InboundMessage(
                from:       $from,
                to:         $to,
                body:       $text !== '' ? $text : 'STOP',
                providerId: $providerId,
                optOutType: 'STOP',
                meta:       $meta,
            )];
        }

        return [new InboundMessage(
            from:       $from,
            to:         $to,
            body:       $text,
            providerId: $providerId,
            meta:       $meta,
        )];
    }

    // --- Internal ---

    private function authHeaders(string $username, string $password): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode("{$username}:{$password}"),
        ];
    }

    private function validateCallbackToken(\WP_REST_Request $request): bool
    {
        $expected = $this->getSharedConfig('callback_token');
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        $supplied = (string) ($request->get_param('token') ?? '');
        if ($supplied === '') {
            return false;
        }
        return hash_equals($expected, $supplied);
    }
}

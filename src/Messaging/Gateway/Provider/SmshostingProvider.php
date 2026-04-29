<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsInboundMessage;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

/**
 * SMShosting — Italian SMS gateway (https://www.smshosting.it/).
 *
 * Auth: HTTP Basic with the account's username/password.
 *
 * Send:    POST https://api.smshosting.it/rest/api/sms/send
 *          (form body: to, from, text — response JSON has transactionId)
 * Account: GET  https://api.smshosting.it/rest/api/user
 *          (response field `italysms` is the Italy SMS credit balance)
 *
 * Inbound MO is delivered as an HTTP GET to a URL configured in the SMShosting
 * dashboard, with `?number=...&text=...` appended. SMShosting does not sign
 * webhooks, so we require a configured `callback_token` and append it as a
 * query arg on the URL we hand back via getInboundCallbackUrl(). Without a
 * token, inbound is rejected.
 *
 * SupportsStatusCallback (DLR) is intentionally not implemented — outbound
 * delivery-receipt webhooks are not documented in the public API.
 */
class SmshostingProvider extends AbstractProvider implements SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    // TODO(verify): SMShosting exposes a full Verify-as-a-Service lifecycle
    // (POST /verify/send, GET /verify/check, POST /verify/command, GET /verify/search).
    // Defer wiring until WSMS lands a SupportsVerify interface.

    private const API_BASE = 'https://api.smshosting.it/rest/api';

    public function getId(): string
    {
        return 'smshosting';
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
                    'label'       => __('Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your SMShosting account username.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your SMShosting account password.', 'wp-sms'),
                ],
                'callback_token' => [
                    'type'        => 'secret',
                    'label'       => __('Callback Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Shared secret appended as ?token=… on the inbound webhook URL configured in SMShosting. Required to enable two-way SMS.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'MyBrand',
                        'description' => __('Numeric sender or alphanumeric ID. Alphanumeric senders to Italian numbers must be registered locally.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'incoming'        => true,
            'unicode'         => true,
            'test_connection' => true,
        ]);
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $from     = (string) $this->getChannelConfig('sms', 'from', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('SMShosting credentials not configured', 'wp-sms'));
        }
        if ($from === '') {
            return DeliveryResult::failed(__('SMShosting Sender ID not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/sms/send', [
            'headers' => $this->authHeaders($username, $password) + [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'to'   => $message->getRecipient(),
                'from' => $from,
                'text' => $message->getBody(),
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] >= 200 && $result['code'] < 300 && is_array($data) && !empty($data['transactionId'])) {
            return DeliveryResult::sent((string) $data['transactionId']);
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid SMShosting credentials', 'wp-sms'));
        }

        $errorCode = is_array($data) ? ($data['errorCode'] ?? null) : null;
        $errorMsg  = is_array($data) ? ($data['errorMsg']  ?? null) : null;

        return DeliveryResult::failed(
            $errorMsg ?: sprintf(__('SMShosting send failed (HTTP %d)', 'wp-sms'), $result['code']),
            meta: array_filter([
                'smshosting_error_code' => $errorCode,
            ]),
        );
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/user', [
            'headers' => $this->authHeaders($username, $password),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }
        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['italysms'])) {
            return null;
        }

        return (string) $data['italysms'];
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('Username and Password are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/user', [
            'headers' => $this->authHeaders($username, $password),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid username or password', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'SMShosting');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = $data['italysms'] ?? 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Italy SMS credit: %s', 'wp-sms'), (string) $balance),
            ['balance' => (string) $balance],
        );
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        $token = (string) $this->getSharedConfig('callback_token', '');
        return RestRoute::url(
            'callbacks/' . $this->getId() . '/inbound',
            $token !== '' ? ['token' => $token] : [],
        );
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        $expected = (string) $this->getSharedConfig('callback_token', '');
        if ($expected === '') {
            return false;
        }
        $given = (string) ($request->get_param('token') ?? '');
        if ($given === '') {
            return false;
        }
        return hash_equals($expected, $given);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $from = $request->get_param('number');
        $body = $request->get_param('text');

        if (empty($from) || $body === null || $body === '') {
            return [];
        }

        // SMShosting's inbound webhook does not include the recipient (our
        // shortcode/longcode), so `to` is left empty.
        return [new InboundMessage(
            from: (string) $from,
            to:   '',
            body: (string) $body,
        )];
    }

    // --- Internal ---

    /** @return array<string, string> */
    private function authHeaders(string $username, string $password): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
        ];
    }
}

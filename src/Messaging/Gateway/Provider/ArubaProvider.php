<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

/**
 * Aruba (smspanel.aruba.it) — Italian SMS provider operated by Aruba S.p.A.
 *
 * Auth: two-step. Basic-auth GET /token returns a persistent
 * `USER_KEY;ACCESS_TOKEN` pair (semicolon-delimited plain text); subsequent
 * calls send those as `user_key` + `Access_token` headers. The token pair is
 * cached in a per-credential transient and refreshed on any 401.
 *
 * DLR: Aruba calls a customer-configured URL with `delivery_date`, `order_id`,
 * `status`, `recipient`. There is no signature — authentication is via a
 * shared `?token=…` query parameter (HelloSMS pattern). The webhook URL is
 * registered in the Aruba panel UI and cannot be set via API.
 *
 * TODO(verify): Aruba exposes /2fa/request + /2fa/verify (managed OTP); defer
 * wiring it through until WSMS gains a SupportsVerify capability.
 */
class ArubaProvider extends AbstractProvider implements SupportsStatusCallback
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://smspanel.aruba.it/API/v1.0/REST';

    private const TOKEN_TRANSIENT_PREFIX = 'wsms_aruba_token_';

    public function getId(): string
    {
        return 'aruba';
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
                    'label'       => __('API Username / Email', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Email or username for your Aruba SMS panel at smspanel.aruba.it.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Password for your Aruba SMS panel.', 'wp-sms'),
                ],
                'callback_token' => [
                    'type'        => 'string',
                    'label'       => __('Callback Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Random secret appended to the DLR callback URL as ?token=… so WSMS can authenticate webhooks. Required if you set up delivery receipts. Aruba does not sign callbacks.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => false,
                        'placeholder' => 'MyBrand',
                        'description' => __('Pre-registered AGCOM-approved alphanumeric alias (max 11 chars). Requires Italian VAT/Codice Fiscale on file with Aruba. Leave blank to use a numeric originator. Aliases work only with the high-quality message type.', 'wp-sms'),
                    ],
                    'message_type' => [
                        'type'        => 'select',
                        'label'       => __('Message Type', 'wp-sms'),
                        'required'    => false,
                        'default'     => 'N',
                        'options'     => [
                            ['value' => 'N', 'label' => __('High quality (with delivery receipt)', 'wp-sms')],
                            ['value' => 'L', 'label' => __('Low cost (160 chars max, no DLR)', 'wp-sms')],
                        ],
                        'description' => __('Aruba routes high-quality messages over premium SMSC and supports alphanumeric senders + delivery receipts. Low-cost is cheaper but capped at 160 chars and does not support aliases.', 'wp-sms'),
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
            return DeliveryResult::failed(__('Aruba credentials not configured', 'wp-sms'));
        }

        $messageType = (string) $this->getChannelConfig('sms', 'message_type', 'N');
        $from        = (string) $this->getChannelConfig('sms', 'from', '');

        $body = [
            'message_type'    => $messageType,
            'message'         => $message->getBody(),
            'recipient'       => [$message->getRecipient()],
            'returnCredits'   => true,
            'returnRemaining' => true,
        ];
        if ($from !== '') {
            $body['sender'] = $from;
        }
        if (!$this->isAscii($message->getBody())) {
            $body['encoding'] = 'ucs2';
        }

        $result = $this->withTokenRetry($username, $password, function (array $token) use ($body) {
            return $this->httpPost(self::API_BASE . '/sms', [
                'headers' => $this->authedHeaders($token) + [
                    'Content-Type' => 'application/json',
                ],
                'body'    => wp_json_encode($body),
            ]);
        });

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 201 && is_array($data) && ($data['result'] ?? null) === 'OK') {
            $orderId = isset($data['order_id']) ? (string) $data['order_id'] : null;
            return DeliveryResult::queued($orderId);
        }

        $error = is_array($data) ? ($data['result'] ?? null) : null;

        return DeliveryResult::failed(
            $error !== null && $error !== ''
                ? sprintf('Aruba: %s', $error)
                : sprintf('Aruba: HTTP %d', $result['code']),
        );
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return null;
        }

        $messageType = (string) $this->getChannelConfig('sms', 'message_type', 'N');

        $result = $this->withTokenRetry($username, $password, function (array $token) {
            return $this->httpGet(self::API_BASE . '/status?getMoney=true&typeAliases=true', [
                'headers' => $this->authedHeaders($token),
            ]);
        });

        if ($result instanceof DeliveryResult) {
            return null;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || empty($data['sms']) || !is_array($data['sms'])) {
            return null;
        }

        $matched = null;
        foreach ($data['sms'] as $bucket) {
            if (is_array($bucket) && ($bucket['type'] ?? null) === $messageType) {
                $matched = $bucket;
                break;
            }
        }

        if ($matched === null) {
            $first = reset($data['sms']);
            $matched = is_array($first) ? $first : null;
        }

        if (!is_array($matched) || !isset($matched['quantity'])) {
            return null;
        }

        return sprintf(
            '%s credits (type %s)',
            (string) $matched['quantity'],
            (string) ($matched['type'] ?? '?'),
        );
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('Aruba username and password are required', 'wp-sms'));
        }

        $result = $this->withTokenRetry($username, $password, function (array $token) {
            return $this->httpGet(self::API_BASE . '/status', [
                'headers' => $this->authedHeaders($token),
            ]);
        });

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(__('Invalid Aruba credentials', 'wp-sms'));
        }

        if ($result['code'] === 401 || $result['code'] === 404) {
            return TestConnectionResult::error(__('Invalid Aruba credentials', 'wp-sms'));
        }

        $data = $this->validateTestResponse($result, 'Aruba');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        return TestConnectionResult::ok(__('Connected to Aruba SMS', 'wp-sms'));
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
        $orderId   = $request->get_param('order_id');
        $rawStatus = (string) ($request->get_param('status') ?? '');

        if (empty($orderId) || $rawStatus === '') {
            return [];
        }

        $upper = strtoupper($rawStatus);
        [$status, $permanent, $isFailure] = match (true) {
            $upper === 'DLVRD'                                                                            => ['delivered', true,  false],
            in_array($upper, ['SENT', 'WAIT4DLVR'], true)                                                 => ['sent',      false, false],
            in_array($upper, ['WAITING', 'SCHEDULED'], true)                                              => ['queued',    false, false],
            in_array($upper, ['ERROR', 'TIMEOUT', 'KO', 'TOOM4USER', 'TOOM4NUM'], true)                   => ['failed',    false, true],
            in_array($upper, ['UNKNRCPT', 'UNKNPFX', 'INVALIDDST', 'INVALIDCONTENTS', 'BLACKLISTED', 'DEMO'], true) => ['failed', true, true],
            default                                                                                       => [strtolower($rawStatus), false, false],
        };

        return [new StatusUpdate(
            providerId:   (string) $orderId,
            status:       $status,
            errorCode:    $isFailure ? $upper : null,
            errorMessage: $isFailure ? sprintf('Aruba: %s', $upper) : null,
            permanent:    $permanent,
        )];
    }

    // --- Internal ---

    /**
     * Run a callable with a fresh Aruba token, retrying once on 401.
     *
     * @param callable(array{user_key:string,access_token:string}): (array|DeliveryResult) $fn
     * @return array|DeliveryResult
     */
    private function withTokenRetry(string $username, string $password, callable $fn): array|DeliveryResult
    {
        $token = $this->getOrFetchToken($username, $password);
        if ($token === null) {
            return DeliveryResult::failed(__('Invalid Aruba credentials', 'wp-sms'));
        }

        $result = $fn($token);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401) {
            $this->invalidateToken($username);
            $token = $this->getOrFetchToken($username, $password);
            if ($token === null) {
                return DeliveryResult::failed(__('Invalid Aruba credentials', 'wp-sms'));
            }
            $result = $fn($token);
        }

        return $result;
    }

    /**
     * @return array{user_key:string,access_token:string}|null
     */
    private function getOrFetchToken(string $username, string $password): ?array
    {
        $key = self::TOKEN_TRANSIENT_PREFIX . md5($username);

        $cached = get_transient($key);
        if (is_array($cached) && !empty($cached['user_key']) && !empty($cached['access_token'])) {
            return [
                'user_key'     => (string) $cached['user_key'],
                'access_token' => (string) $cached['access_token'],
            ];
        }

        // Aruba's /token endpoint issues a long-lived (~6 month) token pair;
        // /login returns a 5-minute idle session key — we always use /token.
        $result = $this->httpGet(self::API_BASE . '/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("{$username}:{$password}"),
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        if ($result['code'] !== 200 && $result['code'] !== 201) {
            return null;
        }

        $body = trim((string) $result['body']);
        if ($body === '' || !str_contains($body, ';')) {
            return null;
        }

        [$userKey, $accessToken] = array_pad(explode(';', $body, 2), 2, '');
        $userKey     = trim($userKey);
        $accessToken = trim($accessToken);

        if ($userKey === '' || $accessToken === '') {
            return null;
        }

        $token = ['user_key' => $userKey, 'access_token' => $accessToken];
        set_transient($key, $token, 30 * 86400);

        return $token;
    }

    private function invalidateToken(string $username): void
    {
        delete_transient(self::TOKEN_TRANSIENT_PREFIX . md5($username));
    }

    /**
     * @param array{user_key:string,access_token:string} $token
     * @return array<string,string>
     */
    private function authedHeaders(array $token): array
    {
        return [
            'user_key'     => $token['user_key'],
            'Access_token' => $token['access_token'],
        ];
    }

    private function isAscii(string $value): bool
    {
        return mb_check_encoding($value, 'ASCII');
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

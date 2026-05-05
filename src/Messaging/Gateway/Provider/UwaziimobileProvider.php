<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Uwazii MOBILE — Kenyan SMS aggregator (restapi.uwaziimobile.com).
 *
 * Auth: 3-step token dance.
 *   1. POST /authorize {username, password} → data.authorization_code
 *   2. POST /accesstoken {authorization_code} → data.access_token
 *   3. Subsequent calls send the token as `X-Access-Token` header.
 * Tokens are cached in a per-credential transient and refreshed on 401.
 *
 * Send body is a JSON ARRAY of message objects (supports batch in one call).
 * No webhook DLR endpoint; status query is poll-based via /sms-full-data.
 */
class UwaziimobileProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://restapi.uwaziimobile.com/v1';

    private const TOKEN_TRANSIENT_PREFIX = 'wsms_uwaziimobile_token_';

    private const TOKEN_TTL = 3000; // 50 minutes; provider TTL undocumented, conservative.

    /** Errors uwaziimobile returns that will not succeed on retry. */
    private const PERMANENT_ERROR_PATTERNS = [
        'no_client_price',
        'no_active_aggregator',
        'invalid_credentials',
        'unauthorized',
    ];

    public function getId(): string
    {
        return 'uwaziimobile';
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
                    'description' => __('Your Uwazii MOBILE portal username from my.uwaziimobile.com.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Uwazii MOBILE portal password. The plugin handles the authorize → accesstoken exchange automatically.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'MyBrand',
                        'description' => __('Pre-approved alphanumeric sender ID. Kenyan senders typically need carrier registration through Uwazii support before live traffic clears.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $from     = (string) $this->getChannelConfig('sms', 'from', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('Uwazii MOBILE credentials not configured', 'wp-sms'));
        }

        $recipient = $this->normalizeRecipient($message->getRecipient());

        $body = [[
            'number'   => [$recipient],
            'senderID' => $from,
            'text'     => $message->getBody(),
            'type'     => 'sms',
        ]];

        $send = function (string $token) use ($body) {
            return $this->httpPost(self::API_BASE . '/send', [
                'headers' => [
                    'X-Access-Token' => $token,
                    'Content-Type'   => 'application/json',
                    'Accept'         => 'application/json',
                ],
                'body' => wp_json_encode($body),
            ]);
        };

        return $this->withTokenRetry($username, $password, $send, function ($result) use ($recipient) {
            return $this->parseSendResult($result, $recipient);
        });
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('Username and Password are required', 'wp-sms'));
        }

        $token = $this->getOrFetchToken($username, $password);
        if ($token === null) {
            return TestConnectionResult::error(__('Invalid Uwazii MOBILE credentials', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/me', [
            'headers' => [
                'X-Access-Token' => $token,
                'Accept'         => 'application/json',
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error($result->error ?? __('Could not reach the Uwazii MOBILE API', 'wp-sms'));
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            $this->invalidateToken($username);
            return TestConnectionResult::error(__('Invalid Uwazii MOBILE credentials', 'wp-sms'));
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from Uwazii MOBILE (HTTP %d)', 'wp-sms'), $result['code'])
            );
        }

        return TestConnectionResult::ok(__('Connected to Uwazii MOBILE', 'wp-sms'));
    }

    // --- Internal ---

    /**
     * @param array{response:array,body:string,code:int}|DeliveryResult $result
     */
    private function parseSendResult(array|DeliveryResult $result, string $recipient): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] >= 500) {
            return DeliveryResult::failed(
                sprintf(__('Uwazii MOBILE: HTTP %d', 'wp-sms'), $result['code']),
                retryable: true,
            );
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] >= 200 && $result['code'] < 300 && is_array($data) && ($data['status'] ?? false) === true) {
            $providerId = $this->extractProviderId($data, $recipient);
            return DeliveryResult::sent($providerId);
        }

        $error = $this->extractErrorMessage($data, $result['code']);
        $retryable = !$this->isPermanentError($error);

        return DeliveryResult::failed(
            sprintf('Uwazii MOBILE: %s', $error),
            retryable: $retryable,
        );
    }

    private function extractProviderId(array $data, string $recipient): ?string
    {
        $entries = $data['data'][$recipient] ?? null;
        if (!is_array($entries) || empty($entries)) {
            return null;
        }

        $first = $entries[0] ?? null;
        if (!is_array($first)) {
            return null;
        }

        $id = $first['id_state'] ?? $first['id'] ?? null;
        return $id !== null ? (string) $id : null;
    }

    private function extractErrorMessage($data, int $httpCode): string
    {
        if (is_array($data)) {
            $errors = $data['errors'] ?? $data['error'] ?? null;
            if (is_string($errors) && $errors !== '') {
                return $errors;
            }
            if (is_array($errors)) {
                return implode('; ', array_map('strval', $errors));
            }
        }
        return sprintf('HTTP %d', $httpCode);
    }

    private function isPermanentError(string $error): bool
    {
        $haystack = strtolower($error);
        foreach (self::PERMANENT_ERROR_PATTERNS as $pattern) {
            if (str_contains($haystack, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Run a request callable with a fresh token, re-authenticating once on 401
     * (or auth-shaped 4xx). Then run the result-parsing callable on the outcome.
     *
     * @param callable(string): (array|DeliveryResult) $send
     * @param callable(array|DeliveryResult): DeliveryResult $parse
     */
    private function withTokenRetry(
        string $username,
        string $password,
        callable $send,
        callable $parse,
    ): DeliveryResult {
        $token = $this->getOrFetchToken($username, $password);
        if ($token === null) {
            return DeliveryResult::failed(__('Invalid Uwazii MOBILE credentials', 'wp-sms'));
        }

        $result = $send($token);

        if (!$result instanceof DeliveryResult && $this->isAuthFailure($result)) {
            $this->invalidateToken($username);
            $token = $this->getOrFetchToken($username, $password);
            if ($token === null) {
                return DeliveryResult::failed(__('Invalid Uwazii MOBILE credentials', 'wp-sms'));
            }
            $result = $send($token);
        }

        return $parse($result);
    }

    /**
     * @param array{response:array,body:string,code:int} $result
     */
    private function isAuthFailure(array $result): bool
    {
        if ($result['code'] === 401 || $result['code'] === 403) {
            return true;
        }
        $data = json_decode($result['body'], true);
        $errors = is_array($data) ? ($data['errors'] ?? $data['error'] ?? null) : null;
        if (!is_string($errors)) {
            return false;
        }
        $lower = strtolower($errors);
        return str_contains($lower, 'unauthorized') || str_contains($lower, 'invalid_credentials') || str_contains($lower, 'token');
    }

    private function getOrFetchToken(string $username, string $password): ?string
    {
        $key = self::TOKEN_TRANSIENT_PREFIX . sha1($username);

        $cached = get_transient($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $authCode = $this->fetchAuthorizationCode($username, $password);
        if ($authCode === null) {
            return null;
        }

        $accessToken = $this->fetchAccessToken($authCode);
        if ($accessToken === null) {
            return null;
        }

        set_transient($key, $accessToken, self::TOKEN_TTL);
        return $accessToken;
    }

    private function invalidateToken(string $username): void
    {
        delete_transient(self::TOKEN_TRANSIENT_PREFIX . sha1($username));
    }

    private function fetchAuthorizationCode(string $username, string $password): ?string
    {
        $result = $this->httpPost(self::API_BASE . '/authorize', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode([
                'username' => $username,
                'password' => $password,
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }
        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        $code = $data['data']['authorization_code'] ?? null;
        return is_string($code) && $code !== '' ? $code : null;
    }

    private function fetchAccessToken(string $authorizationCode): ?string
    {
        $result = $this->httpPost(self::API_BASE . '/accesstoken', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode([
                'authorization_code' => $authorizationCode,
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }
        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        $token = $data['data']['access_token'] ?? null;
        return is_string($token) && $token !== '' ? $token : null;
    }

    private function normalizeRecipient(string $recipient): string
    {
        return preg_replace('/\D+/', '', $recipient) ?? '';
    }
}

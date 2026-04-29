<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\SupportsInboundMessage;
use WSms\Messaging\Contracts\SupportsOptOutDetection;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

class VerimorProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsOptOutDetection,
    SupportsDynamicOptions
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://sms.verimor.com.tr/v2';

    // TODO(verify): Verimor exposes no Verify-as-a-Service endpoint; nothing to defer.
    // TODO(voice): Bulutsantralim (Cloud PBX) lives in a separate repo/API; cross-cutting work.

    public function getId(): string
    {
        return 'verimor';
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
                    'label'       => __('Username (MSISDN)', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your 12-digit Verimor account number, starting with 90.', 'wp-sms'),
                    'placeholder' => '908501234567',
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Set in OIM → SMS Ayarlarım. Different from your panel login password.', 'wp-sms'),
                ],
                'is_commercial' => [
                    'type'        => 'boolean',
                    'label'       => __('Commercial Sending', 'wp-sms'),
                    'default'     => false,
                    'description' => __('Required for IYS-regulated marketing SMS to Turkish recipients.', 'wp-sms'),
                ],
                'iys_recipient_type' => [
                    'type'        => 'select',
                    'label'       => __('IYS Recipient Type', 'wp-sms'),
                    'default'     => 'BIREYSEL',
                    'description' => __('IYS recipient classification. Only used when Commercial Sending is on.', 'wp-sms'),
                    'options'     => [
                        ['value' => 'BIREYSEL', 'label' => __('Individual (BIREYSEL)', 'wp-sms')],
                        ['value' => 'TACIR',    'label' => __('Merchant (TACIR)', 'wp-sms')],
                    ],
                ],
                'callback_token' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Token', 'wp-sms'),
                    'description' => __('Shared secret appended as ?token=… to the DLR/inbound URL configured in OIM. Verimor does not sign callbacks, so this token is the only authentication.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'source_addr' => [
                        'type'        => 'string',
                        'label'       => __('Sender Header', 'wp-sms'),
                        'required'    => true,
                        'dynamic'     => true,
                        'description' => __('Pre-approved alphanumeric header from OIM → Başlıklar. BTK rules forbid numeric headers via API.', 'wp-sms'),
                        'placeholder' => 'BASLIGIM',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');
        $sourceAddr = $this->getChannelConfig('sms', 'source_addr');

        if (!$username || !$password) {
            return DeliveryResult::failed(__('Verimor credentials not configured', 'wp-sms'));
        }
        if (!$sourceAddr) {
            return DeliveryResult::failed(__('Verimor Sender Header not configured', 'wp-sms'));
        }

        $body = [
            'username'    => $username,
            'password'    => $password,
            'source_addr' => $sourceAddr,
            'messages'    => [[
                'msg'  => $message->getBody(),
                'dest' => $message->getRecipient(),
            ]],
        ];

        if ($this->getSharedConfig('is_commercial')) {
            $body['is_commercial']      = true;
            $recipientType              = $this->getSharedConfig('iys_recipient_type');
            $body['iys_recipient_type'] = $recipientType !== '' && $recipientType !== null ? $recipientType : 'BIREYSEL';
        }

        $result = $this->httpPost(self::API_BASE . '/send.json', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => '*/*',
            ],
            'body' => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $responseBody = trim($result['body']);

        if ($result['code'] >= 200 && $result['code'] < 300) {
            // Success body is the campaign id as plain text.
            return DeliveryResult::sent(
                providerId: $responseBody !== '' ? $responseBody : null,
                meta: ['verimor_campaign_id' => $responseBody],
            );
        }

        return DeliveryResult::failed(
            $this->humanizeError($responseBody, $result['code']),
            meta: array_filter([
                'verimor_error_code' => $responseBody !== '' ? $responseBody : null,
                'verimor_http_code'  => $result['code'] ?: null,
            ]),
        );
    }

    public function getCredit(): ?string
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/balance?' . http_build_query([
            'username' => $username,
            'password' => $password,
        ]));

        if ($result instanceof DeliveryResult) {
            return null;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $body = trim($result['body']);
        return ctype_digit($body) ? $body : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return TestConnectionResult::error(__('Username and Password are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/balance?' . http_build_query([
            'username' => $username,
            'password' => $password,
        ]));

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the Verimor API. Check your server\'s internet connection.', 'wp-sms'),
            );
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return TestConnectionResult::error(__('Invalid username or password', 'wp-sms'));
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from Verimor (HTTP %d)', 'wp-sms'), $result['code']),
            );
        }

        $body = trim($result['body']);
        if (!ctype_digit($body)) {
            // The IP allowlist error returns 401 with a Turkish message.
            return TestConnectionResult::error(
                sprintf(__('Verimor: %s', 'wp-sms'), $body !== '' ? $body : __('unknown response', 'wp-sms')),
            );
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $body),
            ['credit' => $body],
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return $this->callbackUrl('status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyCallbackToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $payload = $request->get_json_params();
        if (!is_array($payload) || empty($payload)) {
            return [];
        }

        $updates = [];
        foreach ($payload as $entry) {
            if (!is_array($entry) || ($entry['type'] ?? null) !== 'outbound') {
                continue;
            }

            $messageId  = $entry['message_id']  ?? null;
            $rawStatus  = $entry['status']      ?? null;
            $gsmError   = $entry['gsm_error']   ?? null;

            if (empty($messageId) || empty($rawStatus)) {
                continue;
            }

            $normalized = $this->normalizeStatus((string) $rawStatus);
            $isTerminal = $normalized === 'failed';

            $updates[] = new StatusUpdate(
                providerId:   (string) $messageId,
                status:       $normalized,
                errorCode:    $isTerminal ? (string) $rawStatus : null,
                errorMessage: $isTerminal ? sprintf(
                    'Verimor: %s%s',
                    (string) $rawStatus,
                    ($gsmError !== null && $gsmError !== '' && $gsmError !== '0') ? ' (gsm_error=' . $gsmError . ')' : '',
                ) : null,
                permanent:    $isTerminal && $this->isPermanentVerimorStatus((string) $rawStatus),
            );
        }

        return $updates;
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return $this->callbackUrl('inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyCallbackToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $payload = $request->get_json_params();
        if (!is_array($payload) || empty($payload)) {
            return [];
        }

        $messages = [];
        foreach ($payload as $entry) {
            if (!is_array($entry) || ($entry['type'] ?? null) !== 'inbound') {
                continue;
            }
            $from = $entry['source_addr'] ?? null;
            if (empty($from)) {
                continue;
            }

            $messages[] = new InboundMessage(
                from:       (string) $from,
                to:         (string) ($entry['destination_addr'] ?? ''),
                body:       (string) ($entry['content'] ?? ''),
                providerId: isset($entry['message_id']) ? (string) $entry['message_id'] : null,
                meta:       array_filter([
                    'network'     => $entry['network']     ?? null,
                    'keyword'     => $entry['keyword']     ?? null,
                    'received_at' => $entry['received_at'] ?? null,
                ], static fn($v) => $v !== null && $v !== ''),
            );
        }

        return $messages;
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        $code = $result->meta['verimor_error_code'] ?? null;
        if ($code === null) {
            return false;
        }
        return in_array($code, [
            'BLACKLISTED_DESTINATION_ADDRESS',
            'NOT_ALLOWED_BY_IYS',
        ], true);
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'source_addr' || $section !== 'sms') {
            return [];
        }

        return $this->withConfig($config, function () {
            $username = $this->getSharedConfig('username');
            $password = $this->getSharedConfig('password');

            if (!$username || !$password) {
                return [];
            }

            $result = $this->httpGet(self::API_BASE . '/headers?' . http_build_query([
                'username' => $username,
                'password' => $password,
            ]));

            if ($result instanceof DeliveryResult) {
                return [];
            }

            if ($result['code'] === 401 || $result['code'] === 403) {
                throw new \RuntimeException(__('Invalid Verimor credentials', 'wp-sms'));
            }

            if ($result['code'] < 200 || $result['code'] >= 300) {
                return [];
            }

            $headers = json_decode($result['body'], true);
            if (!is_array($headers)) {
                return [];
            }

            $options = [];
            foreach ($headers as $header) {
                if (!is_string($header) || $header === '') {
                    continue;
                }
                $options[] = ['value' => $header, 'label' => $header];
            }
            return $options;
        });
    }

    // --- Internal ---

    private function callbackUrl(string $kind): string
    {
        $token = $this->getSharedConfig('callback_token');
        $args  = is_string($token) && $token !== '' ? ['token' => $token] : [];
        return RestRoute::url('callbacks/' . $this->getId() . '/' . $kind, $args);
    }

    /**
     * Verimor does not sign callback payloads — auth is by IP allowlist on their side
     * plus a shared token we configure into the URL on ours. If the operator hasn't set
     * a token, we reject all callbacks (per the "reject-by-default" rule).
     */
    private function verifyCallbackToken(\WP_REST_Request $request): bool
    {
        $expected = $this->getSharedConfig('callback_token');
        if (!is_string($expected) || $expected === '') {
            return false;
        }

        $provided = (string) ($request->get_param('token') ?? '');
        if ($provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    private function normalizeStatus(string $raw): string
    {
        return match ($raw) {
            'SENDING', 'WAITING'              => 'sent',
            'DELIVERED', 'SENT'               => 'delivered',
            'NOT_DELIVERED', 'EXPIRED', 'REJECTED',
            'INVALID_DESTINATION_ADDRESS',
            'BLACKLISTED_DESTINATION_ADDRESS',
            'NOT_ALLOWED_BY_IYS',
            'DOUBLE_SEND_ERROR',
            'MISSING_TARIFF',
            'ROUTE_NOT_AVAILABLE',
            'NETWORK_NOTCOVERED',
            'SEND_ERROR',
            'INTERNATIONAL_DENIED'            => 'failed',
            default                           => strtolower($raw),
        };
    }

    private function isPermanentVerimorStatus(string $status): bool
    {
        return in_array($status, [
            'INVALID_DESTINATION_ADDRESS',
            'BLACKLISTED_DESTINATION_ADDRESS',
            'NOT_ALLOWED_BY_IYS',
        ], true);
    }

    private function humanizeError(string $code, int $httpCode): string
    {
        $map = [
            'INSUFFICIENT_CREDITS'              => __('Insufficient credits in your Verimor account.', 'wp-sms'),
            'INVALID_SOURCE_ADDRESS'            => __('The Sender Header was rejected. Approve it in OIM → Başlıklar.', 'wp-sms'),
            'NUMERIC_SOURCE_ADDRESS_NOT_ALLOWED'=> __('Numeric Sender Headers are blocked by BTK; use an alphanumeric header.', 'wp-sms'),
            'MESSAGE_TOO_LONG'                  => __('Message body exceeds Verimor\'s length limit.', 'wp-sms'),
            'INVALID_UTF8'                      => __('Message must be UTF-8 encoded.', 'wp-sms'),
            'MUKERRER_RAPORLAMA'                => __('Duplicate send within 24 hours blocked by Verimor.', 'wp-sms'),
            'FORBIDDEN_MESSAGE'                 => __('Message contains banned keywords.', 'wp-sms'),
            'MISSING_DESTINATION_ADDRESS'       => __('Recipient is missing.', 'wp-sms'),
            'INVALID_DESTINATION_ADDRESS'       => __('Recipient phone number format is invalid.', 'wp-sms'),
            'MISSING_IYS_BRAND_CODE'            => __('Commercial sending requires an IYS brand code on the Sender Header.', 'wp-sms'),
            'INVALID_IYS_RECIPIENT_TYPE'        => __('IYS recipient type must be BIREYSEL or TACIR.', 'wp-sms'),
            'NOT_ALLOWED_BY_IYS'                => __('Recipient has no IYS consent for this Sender Header.', 'wp-sms'),
            'AHS_AUTHORIZATION_ERROR'           => __('IYS AHS authorization missing — grant Verimor AHS permission in IYS.', 'wp-sms'),
            'NO_AHS_BRAND_ERROR'                => __('No IYS-registered brand found for this VKN.', 'wp-sms'),
            'MESSAGE_COUNT_LIMIT_EXCEEDED'      => __('Maximum message count per request (50,000) exceeded.', 'wp-sms'),
            'INVALID_JSON'                      => __('Invalid JSON in request body.', 'wp-sms'),
        ];

        if ($code !== '' && isset($map[$code])) {
            return $map[$code];
        }
        if ($code !== '') {
            return sprintf(__('Verimor error: %s', 'wp-sms'), $code);
        }
        return sprintf(__('Verimor returned HTTP %d', 'wp-sms'), $httpCode);
    }
}

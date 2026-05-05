<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

/**
 * ProSMS (prosms.se) — Swedish SMS gateway operated by Compaya (sister brand of CPSMS).
 *
 * Auth: Bearer token against api.prosms.se.
 * Send: POST /v1/sms/send JSON {receiver, senderName, message, format, encoding,
 *       userReference, [dlrUrl, scheduled]}.
 * Credit: GET /user/getcreditvalue (no /v1 prefix, per the Postman collection).
 * Senders: GET /v1/sendername/list — populates the sender_name dropdown.
 *
 * No inbound MO, no signed webhooks. Delivery reports authenticate via a
 * shared `callback_token` baked into the dlrUrl query string using the same
 * %26 trick as CpsmsProvider, since ProSMS appends `&status=…` server-side.
 */
class ProSmsProvider extends AbstractProvider implements SupportsStatusCallback, SupportsDynamicOptions
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.prosms.se';

    public function getId(): string
    {
        return 'prosms';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generate at login.prosms.se/account/settings/edit#APIKey. Sent as Bearer token to api.prosms.se.', 'wp-sms'),
                ],
                'callback_token' => [
                    'type'        => 'string',
                    'label'       => __('Callback Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Shared secret appended to the delivery-report URL as ?token=… so the receiver can authenticate. Required to enable status callbacks; ProSMS does not sign webhooks.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_name' => [
                        'type'        => 'string',
                        'label'       => __('Sender Name', 'wp-sms'),
                        'required'    => true,
                        'dynamic'     => true,
                        'description' => __('Approved sender name from the ProSMS console. Alphanumeric ≤11 chars or numeric ≤15 digits.', 'wp-sms'),
                        'placeholder' => 'MyBrand',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('ProSMS API key not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender_name', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('ProSMS sender name not configured', 'wp-sms'));
        }

        // ProSMS userReference must be ≤50 chars (error 1046) and is the only handle
        // we get back on DLRs, so it doubles as our providerId.
        $reference = 'wsms-' . bin2hex(random_bytes(13));

        $body = [
            'receiver'      => preg_replace('/\D+/', '', $message->getRecipient()),
            'senderName'    => $sender,
            'message'       => $message->getBody(),
            'format'        => mb_check_encoding($message->getBody(), 'ASCII') ? 'gsm' : 'unicode',
            'encoding'      => 'utf8',
            'userReference' => $reference,
        ];

        $dlrUrl = $this->buildDlrUrl($reference);
        if ($dlrUrl !== null) {
            $body['dlrUrl'] = $dlrUrl;
        }

        $scheduled = $message->getMeta()['scheduled_at'] ?? null;
        if (is_string($scheduled) && $scheduled !== '') {
            $body['scheduled'] = $scheduled;
        }

        $result = $this->httpPost(self::API_BASE . '/v1/sms/send', [
            'headers' => $this->authHeaders($apiKey) + [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $http = (int) $result['code'];
        $data = json_decode($result['body'], true);

        if ($http === 401 || $http === 403) {
            return DeliveryResult::failed(__('Invalid ProSMS API key', 'wp-sms'));
        }

        $code = is_array($data) && isset($data['code']) ? (int) $data['code'] : null;

        // ProSMS success codes are 5000–5014 (per the documented error table).
        // Anything outside that range — including the partial-rejection code 1059
        // and any non-2xx HTTP — is a failure.
        if ($http >= 200 && $http < 300 && $code !== null && $code >= 5000 && $code <= 5014) {
            return DeliveryResult::queued($reference);
        }

        $errorMsg = is_array($data) && isset($data['message'])
            ? (string) $data['message']
            : sprintf('HTTP %d', $http);

        return DeliveryResult::failed(
            $errorMsg,
            meta: array_filter([
                'prosms_code' => $code !== null ? (string) $code : null,
                'prosms_http' => $http ? (string) $http : null,
            ]),
        );
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/user/getcreditvalue', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        if ((int) $result['code'] < 200 || (int) $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['credit'])) {
            return null;
        }

        return (string) $data['credit'];
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/user/getcreditvalue', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if (!$result instanceof DeliveryResult) {
            $code = (int) $result['code'];
            if ($code === 401 || $code === 403) {
                return TestConnectionResult::error(__('Invalid ProSMS API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'ProSMS');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $credit = isset($data['credit']) ? (string) $data['credit'] : null;
        $message = $credit !== null
            ? sprintf(__('Connected to ProSMS — credit: %s', 'wp-sms'), $credit)
            : __('Connected to ProSMS', 'wp-sms');

        return TestConnectionResult::ok($message);
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
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

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $messageId = $request->get_param('messageid');
        $rawStatus = $request->get_param('status');

        if (!$messageId || $rawStatus === null || $rawStatus === '') {
            return [];
        }

        // ProSMS does not pin DLR field names in the public docs; default to the
        // sister-brand convention (status/messageid). Confirm against a live DLR
        // before flipping TESTED to true and adjust if the field shape differs.
        $status = (string) $rawStatus;
        [$normalized, $permanent] = match (strtolower($status)) {
            'delivered'           => ['delivered', false],
            'sent', 'enroute',
            'buffered', 'queued'  => ['sent', false],
            'failed', 'expired',
            'rejected', 'undeliv',
            'undelivered'         => ['failed', true],
            default               => [$status, false],
        };

        return [new StatusUpdate(
            providerId:   (string) $messageId,
            status:       $normalized,
            errorCode:    $normalized === 'failed' ? $status : null,
            errorMessage: $normalized === 'failed' ? sprintf('ProSMS: %s', $status) : null,
            permanent:    $permanent,
        )];
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'sender_name' || $section !== 'sms') {
            return [];
        }

        return $this->withConfig($config, function () {
            $apiKey = $this->getSharedConfig('api_key');
            if (!$apiKey) {
                return [];
            }

            try {
                $data = $this->fetchJsonOrFail(self::API_BASE . '/v1/sendername/list', [
                    'headers' => $this->authHeaders($apiKey),
                ]);
            } catch (\RuntimeException) {
                return [];
            }

            $rows = $data['senderNames'] ?? $data['senders'] ?? $data['data'] ?? $data;
            if (!is_array($rows)) {
                return [];
            }

            $options = [];
            foreach ($rows as $row) {
                $value = is_array($row)
                    ? (string) ($row['senderName'] ?? $row['name'] ?? $row['value'] ?? '')
                    : (string) $row;
                if ($value === '') {
                    continue;
                }
                $options[] = ['value' => $value, 'label' => $value];
            }

            return $options;
        });
    }

    // --- Internal ---

    private function authHeaders(string $apiKey): array
    {
        return [
            'Authorization' => 'Bearer ' . $apiKey,
        ];
    }

    private function buildDlrUrl(string $reference): ?string
    {
        $token = $this->getSharedConfig('callback_token');
        if (!is_string($token) || $token === '') {
            return null;
        }

        // ProSMS appends `&status=…` to the dlr_url. Encoding our own `&` separators
        // as %26 keeps them intact through ProSMS's URL handling so they decode back
        // to a single well-formed query string server-side. Mirrors CpsmsProvider.
        $base = $this->getStatusCallbackUrl();
        $separator = str_contains($base, '?') ? '%26' : '?';
        return $base
            . $separator . 'messageid=' . rawurlencode($reference)
            . '%26token=' . rawurlencode($token);
    }
}

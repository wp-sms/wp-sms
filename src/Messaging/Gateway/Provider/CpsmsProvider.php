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
 * CPSMS (cpsms.dk) — Danish SMS gateway operated by Compaya.
 *
 * Auth: HTTP Basic with username + API key against api.cpsms.dk/v2.
 * Send: POST /v2/send JSON {to, message, from, [reference, dlr_url]}.
 * Credit: GET /v2/creditvalue → {credit}.
 *
 * No inbound MO, no signed webhooks. Delivery reports authenticate via a
 * shared `callback_token` baked into the dlr_url query string using the same
 * %26 trick as SureSmsProvider, since CPSMS appends `&status=…&receiver=…`
 * to the registered URL.
 */
class CpsmsProvider extends AbstractProvider implements SupportsStatusCallback
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.cpsms.dk/v2';

    public function getId(): string
    {
        return 'cpsms';
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
                    'placeholder' => 'your-cpsms-login',
                ],
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generate at app.cpsms.dk under API access. Used as the password in HTTP Basic auth.', 'wp-sms'),
                ],
                'callback_token' => [
                    'type'        => 'string',
                    'label'       => __('Callback Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Shared secret appended to the delivery-report URL as ?token=… so the receiver can authenticate. Required to enable status callbacks; CPSMS does not sign webhooks.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Alphanumeric ≤11 chars or numeric ≤15 chars.', 'wp-sms'),
                        'placeholder' => 'MyBrand',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = $this->getSharedConfig('username');
        $apiKey   = $this->getSharedConfig('api_key');
        if (!$username || !$apiKey) {
            return DeliveryResult::failed(__('CPSMS credentials not configured', 'wp-sms'));
        }

        // CPSMS reference must be ≤32 chars and is the only handle we get back
        // on DLRs (the API returns no message ID on /v2/send), so it doubles
        // as our providerId. 13 random bytes → 26 hex chars + 5-char prefix = 31.
        $reference = 'wsms-' . bin2hex(random_bytes(13));

        $body = [
            'to'        => $message->getRecipient(),
            'message'   => $message->getBody(),
            'from'      => (string) $this->getChannelConfig('sms', 'from', ''),
            'reference' => $reference,
        ];

        $dlrUrl = $this->buildDlrUrl($reference);
        if ($dlrUrl !== null) {
            $body['dlr_url'] = $dlrUrl;
        }

        $result = $this->httpPost(self::API_BASE . '/send', [
            'headers' => $this->authHeaders($username, $apiKey) + [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $code = (int) $result['code'];
        $data = json_decode($result['body'], true);

        if ($code === 401 || $code === 403) {
            return DeliveryResult::failed(__('Invalid CPSMS credentials', 'wp-sms'));
        }

        // 207 is multi-recipient partial success; single-recipient sends still
        // resolve to either a `success` array or an `error` object.
        if (($code === 200 || $code === 207) && is_array($data) && !empty($data['success'])) {
            return DeliveryResult::queued($reference);
        }

        $errorMsg = is_array($data) && isset($data['error']['message'])
            ? (string) $data['error']['message']
            : sprintf('HTTP %d', $code);
        $errorCode = is_array($data) && isset($data['error']['code'])
            ? (string) $data['error']['code']
            : null;

        return DeliveryResult::failed(
            $errorMsg,
            meta: array_filter([
                'cpsms_code' => $errorCode,
                'cpsms_http' => $code ? (string) $code : null,
            ]),
        );
    }

    public function getCredit(): ?string
    {
        $username = $this->getSharedConfig('username');
        $apiKey   = $this->getSharedConfig('api_key');
        if (!$username || !$apiKey) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/creditvalue', [
            'headers' => $this->authHeaders($username, $apiKey),
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
        $username = $this->getSharedConfig('username');
        $apiKey   = $this->getSharedConfig('api_key');
        if (!$username || !$apiKey) {
            return TestConnectionResult::error(__('Username and API Key are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/creditvalue', [
            'headers' => $this->authHeaders($username, $apiKey),
        ]);

        if (!$result instanceof DeliveryResult) {
            $code = (int) $result['code'];
            if ($code === 401 || $code === 403) {
                return TestConnectionResult::error(__('Invalid CPSMS credentials', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'CPSMS');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $credit = isset($data['credit']) ? (string) $data['credit'] : null;
        $message = $credit !== null
            ? sprintf(__('Connected to CPSMS — credit: %s', 'wp-sms'), $credit)
            : __('Connected to CPSMS', 'wp-sms');

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

        // CPSMS DLR codes (per api.cpsms.dk/documentation/):
        //   1 = Delivered (terminal success)
        //   2 = Failed (permanent — wrong number, rejected, etc.)
        //   4 = Buffered (en route, awaiting handset DLR)
        //   8 = Abandoned (permanent — expired without delivery)
        $statusInt = (int) $rawStatus;
        [$status, $permanent] = match ($statusInt) {
            1       => ['delivered', false],
            4       => ['sent', false],
            2, 8    => ['failed', true],
            default => [(string) $rawStatus, false],
        };

        return [new StatusUpdate(
            providerId:   (string) $messageId,
            status:       $status,
            errorCode:    $status === 'failed' ? (string) $statusInt : null,
            errorMessage: $status === 'failed' ? sprintf('CPSMS status %d', $statusInt) : null,
            permanent:    $permanent,
        )];
    }

    // --- Internal ---

    private function authHeaders(string $username, string $apiKey): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode("{$username}:{$apiKey}"),
        ];
    }

    private function buildDlrUrl(string $reference): ?string
    {
        $token = $this->getSharedConfig('callback_token');
        if (!is_string($token) || $token === '') {
            return null;
        }

        // CPSMS appends `&status=…&receiver=…` to the dlr_url. Encoding our
        // own `&` separators as %26 keeps them intact through CPSMS's URL
        // handling so they decode back to a single well-formed query string
        // server-side. Mirrors SureSmsProvider's approach.
        $base = $this->getStatusCallbackUrl();
        $separator = str_contains($base, '?') ? '%26' : '?';
        return $base
            . $separator . 'messageid=' . rawurlencode($reference)
            . '%26token=' . rawurlencode($token);
    }
}

<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\SupportsInboundMessage;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

/**
 * SureSMS (suresms.com) — Danish SMS gateway.
 *
 * Auth: OAuth2 password grant — POST /oauth2/api/Account/Gettoken with
 * `accountPhoneNumber: "APIkey"` + `accountPassword: <api_key>`. Tokens are
 * cached via WordPress transients keyed by hashed api_key and refreshed
 * automatically on 401.
 *
 * Webhooks (status DLR + inbound) are not signed; this provider authenticates
 * them via a shared `callback_token` appended as `?token=…` to each callback
 * URL, matching the HelloSmsProvider pattern. Status callbacks are configured
 * per-message via the `statusWebhook` field on /api/Message/SendExtended.
 * Inbound webhooks are operator-registered via the SureSMS dashboard.
 *
 * SMS-only: SureSMS exposes no WhatsApp/RCS/Voice/MMS endpoints. The legacy
 * /Script/SendSMS.aspx XML endpoint (used by v7) is bypassed in favour of the
 * modern OAuth2 REST API documented at developer.suresms.com.
 */
class SureSmsProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsDynamicOptions
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.suresms.com/oauth2';
    private const TOKEN_TRANSIENT_PREFIX = 'wsms_suresms_token_';
    private const TOKEN_GRACE_SECONDS = 60;
    private const TOKEN_DEFAULT_TTL = 86400;

    public function getId(): string
    {
        // TODO(voice): SureSMS exposes only SMS today; revisit if the API
        // adds Voice/Verify endpoints.
        return 'suresms';
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
                    'description' => __('Generate at app.suresms.com/UserApi/Index. The API key is sent as the password to /api/Account/Gettoken with the literal string "APIkey" as the username.', 'wp-sms'),
                ],
                'callback_token' => [
                    'type'        => 'string',
                    'label'       => __('Callback Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Shared secret appended to status and inbound webhook URLs as ?token=… so the receiver can authenticate the caller. SureSMS does not sign webhooks.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Approved sender ID. Alphanumeric IDs require manual approval at app.suresms.com (up to 24h). Leave blank to use the account default.', 'wp-sms'),
                        'placeholder' => 'MyBrand',
                        'dynamic'     => true,
                    ],
                ],
            ],
        ];
    }

    // SureSMS modern API has no balance endpoint; getCredit() inherits the
    // AbstractProvider default of returning null. The legacy XML
    // /Script/GetUserBalance.aspx endpoint is intentionally not used here.

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        // TODO(verify): SureSMS has no Verify-as-a-Service endpoint; revisit
        // if a /verify/start endpoint is added (would need SupportsVerify).

        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('SureSMS API key not configured', 'wp-sms'));
        }

        try {
            $token = $this->getAccessToken();
        } catch (\RuntimeException $e) {
            return DeliveryResult::failed($e->getMessage());
        }

        $messageId = $this->generateMessageId();
        $body = $this->buildSendBody($message, $messageId);

        $result = $this->postJson(self::API_BASE . '/api/Message/SendExtended', $body, $token);

        // On 401 the cached token may have been revoked server-side; drop it
        // and retry once with a freshly fetched token.
        if (!$result instanceof DeliveryResult && (int) $result['code'] === 401) {
            $this->forgetCachedToken();
            try {
                $token = $this->getAccessToken();
            } catch (\RuntimeException $e) {
                return DeliveryResult::failed($e->getMessage());
            }
            $result = $this->postJson(self::API_BASE . '/api/Message/SendExtended', $body, $token);
        }

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        return $this->parseSendResponse($result, $messageId);
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        try {
            $token = $this->getAccessToken();
        } catch (\RuntimeException $e) {
            return TestConnectionResult::error($e->getMessage());
        }

        $result = $this->httpGet(self::API_BASE . '/api/User/SenderId', [
            'headers' => $this->bearerHeaders($token),
        ]);

        if (!$result instanceof DeliveryResult) {
            $code = (int) $result['code'];
            if ($code === 401 || $code === 403) {
                $this->forgetCachedToken();
                return TestConnectionResult::error(__('Invalid SureSMS API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'SureSMS');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        return TestConnectionResult::ok(__('Connected to SureSMS', 'wp-sms'));
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
        $messageId = $request->get_param('messageid') ?? $request->get_param('messageId');
        $rawStatus = $request->get_param('Status') ?? $request->get_param('status');

        if (!$messageId || $rawStatus === null || $rawStatus === '') {
            return [];
        }

        // SureSMS status codes (per developer.suresms.com/https/https-callbackurl/):
        //   1 = Delivered (terminal success)
        //   2 = Permanent failure: wrong number, rejected, credit limit, etc.
        //   4 = Enroute: dispatched to operator, awaiting handset DLR
        //   8 = Permanent failure: expired, deleted, SPAM filter, etc.
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
            errorMessage: $status === 'failed' ? sprintf('SureSMS status %d', $statusInt) : null,
            permanent:    $permanent,
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
        // Modern WebHook subscription posts a JSON body matching WebhookResponse.
        $payload = $request->get_json_params();
        if (is_array($payload) && !empty($payload)) {
            return $this->parseModernInbound($payload);
        }

        // Legacy 2-way endpoint (operator-registered via SureSMS support):
        // GET with receivedutcdatetime, receivedfromphonenumber,
        // receivedbyphonenumber, body.
        return $this->parseLegacyInbound($request);
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'from' || $section !== 'sms') {
            return [];
        }

        return $this->withConfig($config, function () {
            $token = $this->getAccessToken();

            $data = $this->fetchJsonOrFail(self::API_BASE . '/api/User/SenderId', [
                'headers' => $this->bearerHeaders($token),
            ]);

            // ApiResponse[List[SenderIdItem]] → entries live at $data['data'].
            $entries = $data['data'] ?? (isset($data[0]) ? $data : []);
            if (!is_array($entries)) {
                return [];
            }

            $options = [];
            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $name = $entry['senderID'] ?? null;
                if (!is_string($name) || $name === '') {
                    continue;
                }
                // Only approved (validFrom set) and unexpired (validTo null) IDs.
                $validFrom = $entry['validFromDateTime'] ?? null;
                $validTo   = $entry['validToDateTime'] ?? null;
                if (empty($validFrom) || !empty($validTo)) {
                    continue;
                }
                $options[] = ['value' => $name, 'label' => $name];
            }
            return $options;
        });
    }

    // --- Internal: send helpers ---

    private function buildSendBody(MessageInterface $message, string $messageId): array
    {
        $body = [
            'toPhonenumber'        => [$message->getRecipient()],
            'messageText'          => $message->getBody(),
            'messageID'            => $messageId,
            'includeOptOutMessage' => false,
        ];

        $from = $this->getChannelConfig('sms', 'from');
        if (is_string($from) && $from !== '') {
            $body['senderID'] = $from;
        }

        $statusUrl = $this->buildStatusWebhookUrl($messageId);
        if ($statusUrl !== null) {
            $body['statusWebhook'] = $statusUrl;
        }

        return $body;
    }

    private function buildStatusWebhookUrl(string $messageId): ?string
    {
        $token = $this->getSharedConfig('callback_token');
        if (!is_string($token) || $token === '') {
            return null;
        }

        // SureSMS appends its own params (Status, StatusUTCDateTime, Receiver)
        // to the URL. The trailing %26 (URL-encoded `&`) ensures their `&Status=…`
        // joins our query string cleanly — pattern documented at
        // developer.suresms.com/https/https-callbackurl/.
        $base = $this->getStatusCallbackUrl();
        $separator = str_contains($base, '?') ? '%26' : '?';
        return $base
            . $separator . 'messageid=' . rawurlencode($messageId)
            . '%26token=' . rawurlencode($token)
            . '%26';
    }

    /**
     * @param array{response: array, body: string, code: int} $result
     */
    private function parseSendResponse(array $result, string $messageId): DeliveryResult
    {
        $code = (int) $result['code'];
        $data = json_decode($result['body'], true);

        if ($code === 401 || $code === 403) {
            return DeliveryResult::failed(__('Invalid SureSMS API key', 'wp-sms'));
        }

        $statusCode = is_array($data) ? ($data['statusCode'] ?? null) : null;
        $statusMessage = is_array($data) ? ($data['statusMessage'] ?? null) : null;

        if ($code >= 200 && $code < 300 && (int) $statusCode === 0) {
            return DeliveryResult::queued($messageId);
        }

        $error = is_string($statusMessage) && $statusMessage !== ''
            ? $statusMessage
            : sprintf('HTTP %d', $code);

        return DeliveryResult::failed($error, meta: array_filter([
            'suresms_code'    => $statusCode !== null ? (string) $statusCode : null,
            'suresms_http'    => (string) $code,
        ]));
    }

    /**
     * @return array{response: array, body: string, code: int}|DeliveryResult
     */
    private function postJson(string $url, array $body, string $token): array|DeliveryResult
    {
        return $this->httpPost($url, [
            'headers' => $this->bearerHeaders($token) + [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);
    }

    // --- Internal: auth ---

    /**
     * @throws \RuntimeException
     */
    private function getAccessToken(): string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!is_string($apiKey) || $apiKey === '') {
            throw new \RuntimeException(__('SureSMS API key not configured', 'wp-sms'));
        }

        $cacheKey = $this->tokenCacheKey($apiKey);
        $cached = get_transient($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $result = $this->httpPost(self::API_BASE . '/api/Account/Gettoken', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode([
                'accountPhoneNumber' => 'APIkey',
                'accountPassword'    => $apiKey,
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            throw new \RuntimeException($result->error ?? __('Could not reach SureSMS', 'wp-sms'));
        }

        $code = (int) $result['code'];
        if ($code === 400 || $code === 401 || $code === 403) {
            throw new \RuntimeException(__('Invalid SureSMS API key', 'wp-sms'));
        }
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException(sprintf(__('SureSMS auth failed (HTTP %d)', 'wp-sms'), $code));
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            throw new \RuntimeException(__('Invalid response from SureSMS auth endpoint', 'wp-sms'));
        }

        // ApiResponse[AccessTokenTokenData]: token nested under data.
        $tokenData = $data['data'] ?? $data;
        $token = is_array($tokenData) ? ($tokenData['token'] ?? null) : null;

        if (!is_string($token) || $token === '') {
            $message = is_array($tokenData) ? ($tokenData['statusMessage'] ?? null) : null;
            throw new \RuntimeException($message ?: __('SureSMS did not return an access token', 'wp-sms'));
        }

        $expiresIn = $this->parseExpiresIn(is_array($tokenData) ? ($tokenData['expires'] ?? null) : null);
        $ttl = max(60, $expiresIn - self::TOKEN_GRACE_SECONDS);
        set_transient($cacheKey, $token, $ttl);

        return $token;
    }

    private function forgetCachedToken(): void
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (is_string($apiKey) && $apiKey !== '') {
            delete_transient($this->tokenCacheKey($apiKey));
        }
    }

    private function tokenCacheKey(string $apiKey): string
    {
        return self::TOKEN_TRANSIENT_PREFIX . sha1($apiKey);
    }

    private function parseExpiresIn(mixed $expires): int
    {
        if (is_int($expires) || (is_string($expires) && ctype_digit($expires))) {
            return (int) $expires;
        }
        if (is_string($expires) && $expires !== '') {
            $ts = strtotime($expires);
            if ($ts !== false) {
                return max(0, $ts - time());
            }
        }
        return self::TOKEN_DEFAULT_TTL;
    }

    private function bearerHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    // --- Internal: callback validation ---

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

    // --- Internal: inbound parsing ---

    /** @return InboundMessage[] */
    private function parseModernInbound(array $payload): array
    {
        $from = $this->reconstructFromPhone($payload);
        if ($from === null) {
            return [];
        }

        $to   = (string) ($payload['toPhoneNumberWithCountryCode'] ?? '');
        $text = (string) ($payload['messageText'] ?? '');

        $optOut = !empty($payload['optoutFromGroupId']) || $this->bodyLooksLikeStop($text);

        $meta = array_filter([
            'received_at'  => $payload['receivedDateTime'] ?? null,
            'optin_group'  => $payload['optinToGroupName'] ?? null,
            'optout_group' => $payload['optoutFromGroupName'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        return [new InboundMessage(
            from:       $from,
            to:         $to,
            body:       $text !== '' ? $text : ($optOut ? 'STOP' : ''),
            providerId: null,
            optOutType: $optOut ? 'STOP' : null,
            meta:       $meta,
        )];
    }

    /** @return InboundMessage[] */
    private function parseLegacyInbound(\WP_REST_Request $request): array
    {
        $from = (string) ($request->get_param('receivedfromphonenumber') ?? '');
        if ($from === '') {
            return [];
        }

        $to   = (string) ($request->get_param('receivedbyphonenumber') ?? '');
        $body = (string) ($request->get_param('body') ?? '');
        $isStop = $this->bodyLooksLikeStop($body);

        $meta = array_filter([
            'received_at' => $request->get_param('receivedutcdatetime'),
        ], static fn ($v) => $v !== null && $v !== '');

        return [new InboundMessage(
            from:       $from,
            to:         $to,
            body:       $body,
            providerId: null,
            optOutType: $isStop ? 'STOP' : null,
            meta:       $meta,
        )];
    }

    private function reconstructFromPhone(array $payload): ?string
    {
        $combined = $payload['fromPhoneNumberWithCountryCodeAndContactName'] ?? null;
        if (is_string($combined) && $combined !== '') {
            // Field may carry "+4520202020 (Contact Name)"; strip parenthetical.
            $stripped = trim(preg_replace('/\s*\(.*$/', '', $combined));
            if ($stripped !== '') {
                return $stripped;
            }
        }

        $cc  = trim((string) ($payload['fromCountryCode'] ?? ''));
        $num = trim((string) ($payload['fromPhoneNumber'] ?? ''));
        if ($num === '') {
            return null;
        }
        if ($cc !== '') {
            $cc = '+' . ltrim($cc, '+');
            return $cc . $num;
        }
        return $num;
    }

    private function bodyLooksLikeStop(string $body): bool
    {
        $upper = strtoupper(trim($body));
        return in_array($upper, ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'], true);
    }

    // --- Internal: misc ---

    private function generateMessageId(): string
    {
        return 'wsms-' . bin2hex(random_bytes(16));
    }
}

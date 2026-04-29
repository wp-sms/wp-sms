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
 * SMSGlobal — Australian-headquartered, globally-routing SMS/MMS aggregator.
 *
 * Auth: MAC SHA-256 over the canonical string [ts, nonce, METHOD, path, host,
 * port, ext='']. Header: Authorization: MAC id="...", ts="...", nonce="...",
 * mac="..." — verified against the official Node SDK and the v7 production
 * class on the development branch.
 *
 * Channels:
 *   - sms via REST POST /v2/sms (MAC-authenticated JSON)
 *   - mms via legacy POST /mms/sendmms.php (HTTP Basic, multipart). The MMS
 *     endpoint's exact auth credentials are not formally documented; this
 *     implementation reuses the api_key/api_secret pair and may need to be
 *     switched to MXT email/password if the endpoint rejects them.
 *
 * Webhooks: SMSGlobal does not document a signature scheme for either DLR
 * (notifyUrl) or inbound (incomingUrl) callbacks. Authenticity is enforced
 * by appending a derived HMAC token (hash_hmac('sha256', 'smsglobal-callback',
 * api_secret)) as a `?token=...` query arg, matching the AfricasTalking and
 * LabsMobile providers.
 *
 * Out of scope: Voice/TTS (no documented endpoint), Verify-style OTP API
 * (deferred per workflow), templates (no list-templates or send-by-id API),
 * DLT/MIIT regulatory IDs (not advertised), opt-out detection (no
 * deterministic error code exposed).
 */
class SmsGlobalProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const REST_BASE = 'https://api.smsglobal.com/v2';
    private const MMS_URL   = 'https://api.smsglobal.com/mms/sendmms.php';
    private const MAC_HOST  = 'api.smsglobal.com';
    private const MAC_PORT  = '443';

    /** Per SMSGlobal MMS API docs: total payload (text + attachments) capped at 300 KB. */
    private const MMS_MAX_BYTES = 300 * 1024;

    public function getId(): string
    {
        return 'smsglobal';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'mms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Issued under MXT → Settings → API Keys.', 'wp-sms'),
                ],
                'api_secret' => [
                    'type'        => 'secret',
                    'label'       => __('API Secret', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Paired with the API Key. Used to sign MAC headers on REST calls and to derive webhook tokens.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from_number' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'BRAND or +61400000000',
                        'description' => __('Registered alphanumeric brand or virtual mobile number.', 'wp-sms'),
                    ],
                ],
                'mms' => [
                    'from_number' => [
                        'type'        => 'string',
                        'label'       => __('MMS Sender Number', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => '+61400000000',
                        'description' => __('Numeric virtual number registered for MMS — alphanumeric senders are not supported on MMS.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        return match ($message->getChannel()) {
            'sms'   => $this->sendSms($message),
            'mms'   => $this->sendMms($message),
            default => DeliveryResult::failed(sprintf(__('Unsupported SMSGlobal channel: %s', 'wp-sms'), $message->getChannel())),
        };
    }

    private function sendSms(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        $apiSecret = $this->getSharedConfig('api_secret');
        $from = $this->getChannelConfig('sms', 'from_number');

        if (!$apiKey || !$apiSecret) {
            return DeliveryResult::failed(__('SMSGlobal credentials not configured', 'wp-sms'));
        }
        if (empty($from)) {
            return DeliveryResult::failed(__('SMSGlobal sender ID not configured for SMS', 'wp-sms'));
        }

        $body = [
            'origin'       => $from,
            'destinations' => [$message->getRecipient()],
            'message'      => $message->getBody(),
            'notifyUrl'    => $this->getStatusCallbackUrl(),
            'incomingUrl'  => $this->getInboundCallbackUrl(),
        ];

        $result = $this->httpPost(self::REST_BASE . '/sms', [
            'headers' => [
                'Authorization' => $this->buildMacHeader('POST', '/v2/sms', $apiKey, $apiSecret),
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 402 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid SMSGlobal API Key or Secret', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        // SMSGlobal returns HTTP 200 with an inner `code` field on validation errors.
        if (isset($data['code']) && (string) $data['code'] !== '200') {
            return DeliveryResult::failed(
                $data['message'] ?? sprintf(__('SMSGlobal send failed (code %s)', 'wp-sms'), $data['code']),
                meta: array_filter(['smsglobal_code' => (string) $data['code']]),
            );
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(
                $data['message'] ?? sprintf('HTTP %d', $result['code']),
            );
        }

        $providerId = $data['messages'][0]['id'] ?? null;

        return DeliveryResult::sent(providerId: $providerId !== null ? (string) $providerId : null);
    }

    private function sendMms(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        $apiSecret = $this->getSharedConfig('api_secret');
        $from = $this->getChannelConfig('mms', 'from_number');

        if (!$apiKey || !$apiSecret) {
            return DeliveryResult::failed(__('SMSGlobal credentials not configured', 'wp-sms'));
        }
        if (empty($from)) {
            return DeliveryResult::failed(__('SMSGlobal sender number not configured for MMS', 'wp-sms'));
        }

        $mediaUrls = $message->getMeta()['media_urls'] ?? [];
        if (!is_array($mediaUrls) || empty($mediaUrls)) {
            return DeliveryResult::failed(__('SMSGlobal MMS requires at least one media URL in message meta.media_urls', 'wp-sms'));
        }

        $attachments = [];
        $totalBytes = strlen($message->getBody());

        foreach ($mediaUrls as $url) {
            $fetched = $this->fetchMediaAttachment((string) $url);
            if ($fetched instanceof DeliveryResult) {
                return $fetched;
            }
            $totalBytes += strlen($fetched['content']);
            if ($totalBytes > self::MMS_MAX_BYTES) {
                return DeliveryResult::failed(
                    sprintf(__('SMSGlobal MMS payload exceeds 300 KB limit (got %d bytes)', 'wp-sms'), $totalBytes),
                );
            }
            $attachments[] = $fetched;
        }

        $boundary = '----WSmsMmsBoundary' . wp_generate_password(16, false);
        $body = $this->buildMmsMultipartBody($boundary, [
            'to'      => $message->getRecipient(),
            'from'    => $from,
            'subject' => (string) ($message->getMeta()['subject'] ?? ''),
            'text'    => $message->getBody(),
        ], $attachments);

        $result = $this->httpPost(self::MMS_URL, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($apiKey . ':' . $apiSecret),
                'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body' => $body,
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid SMSGlobal MMS credentials', 'wp-sms'));
        }
        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(
                trim($result['body']) !== '' ? trim($result['body']) : sprintf('HTTP %d', $result['code']),
            );
        }

        // sendmms.php returns plain text (e.g. "OK: 12345") — extract a numeric ID if present.
        $providerId = null;
        if (preg_match('/(\d{4,})/', $result['body'], $m)) {
            $providerId = $m[1];
        }

        return DeliveryResult::sent(providerId: $providerId);
    }

    /**
     * @return array{filename:string, mime:string, content:string}|DeliveryResult
     */
    private function fetchMediaAttachment(string $url): array|DeliveryResult
    {
        $result = $this->httpGet($url);
        if ($result instanceof DeliveryResult) {
            return DeliveryResult::failed(sprintf(__('Could not fetch MMS media: %s', 'wp-sms'), $url));
        }
        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(sprintf(__('MMS media fetch returned HTTP %d for %s', 'wp-sms'), $result['code'], $url));
        }

        $mime = wp_remote_retrieve_header($result['response'], 'content-type') ?: 'application/octet-stream';
        $filename = basename(parse_url($url, PHP_URL_PATH) ?: 'attachment');
        if ($filename === '' || $filename === '/') {
            $filename = 'attachment';
        }

        return [
            'filename' => $filename,
            'mime'     => (string) $mime,
            'content'  => $result['body'],
        ];
    }

    /**
     * @param array<string, string> $fields
     * @param array<int, array{filename:string, mime:string, content:string}> $attachments
     */
    private function buildMmsMultipartBody(string $boundary, array $fields, array $attachments): string
    {
        $lines = [];
        foreach ($fields as $name => $value) {
            if ($value === '') {
                continue;
            }
            $lines[] = '--' . $boundary;
            $lines[] = 'Content-Disposition: form-data; name="' . $name . '"';
            $lines[] = '';
            $lines[] = $value;
        }
        foreach ($attachments as $i => $att) {
            $lines[] = '--' . $boundary;
            $lines[] = 'Content-Disposition: form-data; name="attachment' . ($i > 0 ? ($i + 1) : '') . '"; filename="' . $att['filename'] . '"';
            $lines[] = 'Content-Type: ' . $att['mime'];
            $lines[] = '';
            $lines[] = $att['content'];
        }
        $lines[] = '--' . $boundary . '--';
        $lines[] = '';

        return implode("\r\n", $lines);
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        $apiSecret = $this->getSharedConfig('api_secret');
        if (!$apiKey || !$apiSecret) {
            return null;
        }

        $result = $this->httpGet(self::REST_BASE . '/user/credit-balance', [
            'headers' => [
                'Authorization' => $this->buildMacHeader('GET', '/v2/user/credit-balance', $apiKey, $apiSecret),
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['balance'])) {
            return null;
        }

        return trim($data['balance'] . ' ' . ($data['currency'] ?? ''));
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        $apiSecret = $this->getSharedConfig('api_secret');
        if (!$apiKey || !$apiSecret) {
            return TestConnectionResult::error(__('API Key and API Secret are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::REST_BASE . '/user/credit-balance', [
            'headers' => [
                'Authorization' => $this->buildMacHeader('GET', '/v2/user/credit-balance', $apiKey, $apiSecret),
            ],
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid API Key or API Secret', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'SMSGlobal');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = isset($data['balance']) ? trim($data['balance'] . ' ' . ($data['currency'] ?? '')) : 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s', 'wp-sms'), $balance),
            ['balance' => $balance],
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url(
            'callbacks/' . $this->getId() . '/status',
            ['token' => $this->callbackToken()],
        );
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        if (!$this->getSharedConfig('api_secret')) {
            return false;
        }
        return hash_equals($this->callbackToken(), (string) ($request->get_param('token') ?? ''));
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $msgid = $request->get_param('msgid');
        if (empty($msgid)) {
            return [];
        }

        $dlrStatus = (string) ($request->get_param('dlrstatus') ?? '');
        $dlrErr = $request->get_param('dlr_err');

        $normalized = match ($dlrStatus) {
            'DELIVRD'            => 'delivered',
            'EXPIRED', 'UNDELIV' => 'failed',
            default              => 'sent',
        };

        return [new StatusUpdate(
            providerId:   (string) $msgid,
            status:       $normalized,
            errorCode:    $dlrErr !== null && $dlrErr !== '' ? (string) $dlrErr : null,
            errorMessage: $normalized === 'failed'
                ? sprintf('SMSGlobal DLR: %s%s', $dlrStatus, ($dlrErr !== null && $dlrErr !== '') ? " (err {$dlrErr})" : '')
                : null,
            permanent:    $dlrStatus === 'UNDELIV',
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url(
            'callbacks/' . $this->getId() . '/inbound',
            ['token' => $this->callbackToken()],
        );
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        if (!$this->getSharedConfig('api_secret')) {
            return false;
        }
        return hash_equals($this->callbackToken(), (string) ($request->get_param('token') ?? ''));
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $from = (string) ($request->get_param('from') ?? '');
        if ($from === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($request->get_param('to') ?? ''),
            body:       (string) ($request->get_param('msg') ?? ''),
            providerId: $request->get_param('msgid'),
            meta:       array_filter(['date' => $request->get_param('date')]),
        )];
    }

    // --- Internal ---

    private function buildMacHeader(string $method, string $path, string $apiKey, string $apiSecret): string
    {
        $ts = (string) time();
        $nonce = (string) random_int(0, PHP_INT_MAX);
        $canonical = implode("\n", [$ts, $nonce, $method, $path, self::MAC_HOST, self::MAC_PORT, '']) . "\n";
        $mac = base64_encode(hash_hmac('sha256', $canonical, $apiSecret, true));

        return 'MAC id="' . $apiKey . '", ts="' . $ts . '", nonce="' . $nonce . '", mac="' . $mac . '"';
    }

    private function callbackToken(): string
    {
        return hash_hmac('sha256', 'smsglobal-callback', (string) $this->getSharedConfig('api_secret'));
    }
}

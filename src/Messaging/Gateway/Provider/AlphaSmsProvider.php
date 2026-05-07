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
 * AlphaSMS — Ukrainian aggregator with global delivery (SMS + Viber + RCS).
 *
 * One endpoint: POST https://alphasms.ua/api/json.php with a JSON body
 * { auth: <key>, data: [<record>, ...] }. Each record's `type` selects the
 * channel (sms, viber, rcs) and `id` is a client-side correlation id; the
 * provider returns msg_id under data[0].data.msg_id.
 *
 * TODO(verify): AlphaSMS exposes type:"whatsapp" and type:"telegram" as
 *   OTP-only Verify-as-a-Service endpoints. Wire through SupportsVerify when
 *   that interface lands (Twilio/Vonage/Plivo Verify carry the same TODO).
 * TODO(voice): no WSMS voice channel yet (see SevenProvider.php). AlphaSMS
 *   has voice/text-to-speech docs we can wire once a Voice channel exists.
 * TODO: SupportsInboundMessage — AlphaSMS Viber 2-way replies are pull-based
 *   (type:"status" on /viber/status_2way), not push. Defer until WSMS gains
 *   a polling-MO abstraction.
 * TODO(carousel): Viber type=carousel requires structured meta['cards']
 *   (2-5 cards with image/url/button). Defer until WSMS message meta gains
 *   a structured rich-content slot.
 */
class AlphaSmsProvider extends AbstractProvider implements SupportsStatusCallback
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_URL = 'https://alphasms.ua/api/json.php';

    public function getId(): string
    {
        return 'alphasms';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'viber', 'rcs'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generated under Settings → API in your AlphaSMS panel.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('SMS Sender ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Pre-approved alphanumeric sender (3–11 Latin letters/digits).', 'wp-sms'),
                    ],
                ],
                'viber' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Viber Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Pre-approved Viber business sender.', 'wp-sms'),
                    ],
                ],
                'rcs' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('RCS Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Pre-approved RCS brand sender.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('AlphaSMS API key not configured', 'wp-sms'));
        }

        $channel = $message->getChannel();
        $record = match ($channel) {
            'sms'   => $this->buildSmsRecord($message),
            'viber' => $this->buildViberRecord($message),
            'rcs'   => $this->buildRcsRecord($message),
            default => null,
        };

        if ($record === null) {
            return DeliveryResult::failed(sprintf(__('AlphaSMS does not support channel %s', 'wp-sms'), $channel));
        }

        if ($record instanceof DeliveryResult) {
            return $record;
        }

        return $this->postAndParse($apiKey, $record);
    }

    private function buildSmsRecord(MessageInterface $message): array
    {
        $record = [
            'type'        => 'sms',
            'id'          => $this->correlationId(),
            'phone'       => $this->normalizePhone($message->getRecipient()),
            'sms_message' => $message->getBody(),
            'hook'        => $this->getStatusCallbackUrl(),
        ];

        $sender = $this->getChannelConfig('sms', 'sender_id');
        if (!empty($sender)) {
            $record['sms_signature'] = (string) $sender;
        }

        return $record;
    }

    private function buildViberRecord(MessageInterface $message): array|DeliveryResult
    {
        $sender = $this->getChannelConfig('viber', 'sender_id');
        if (empty($sender)) {
            return DeliveryResult::failed(__('AlphaSMS Viber Sender ID is required', 'wp-sms'));
        }

        $meta = $message->getMeta();
        $record = [
            'type'            => 'viber',
            'id'              => $this->correlationId(),
            'phone'           => $this->normalizePhone($message->getRecipient()),
            'viber_signature' => (string) $sender,
            'viber_message'   => $message->getBody(),
            'viber_lifetime'  => isset($meta['viber_lifetime']) ? (int) $meta['viber_lifetime'] : 172800,
            'hook'            => $this->getStatusCallbackUrl(),
        ];

        $mediaUrl   = $meta['media_url']   ?? null;
        $linkUrl    = $meta['link_url']    ?? null;
        $buttonText = $meta['button_text'] ?? null;

        if ($mediaUrl && $linkUrl && $buttonText) {
            $record['viber_type']   = 'text+image+link';
            $record['viber_image']  = (string) $mediaUrl;
            $record['viber_link']   = (string) $linkUrl;
            $record['viber_button'] = (string) $buttonText;
        } elseif ($linkUrl && $buttonText) {
            $record['viber_type']   = 'text+link';
            $record['viber_link']   = (string) $linkUrl;
            $record['viber_button'] = (string) $buttonText;
        } elseif ($mediaUrl) {
            $record['viber_type']  = 'image';
            $record['viber_image'] = (string) $mediaUrl;
        } else {
            $record['viber_type'] = 'text';
        }

        return $record;
    }

    private function buildRcsRecord(MessageInterface $message): array|DeliveryResult
    {
        $sender = $this->getChannelConfig('rcs', 'sender_id');
        if (empty($sender)) {
            return DeliveryResult::failed(__('AlphaSMS RCS Sender ID is required', 'wp-sms'));
        }

        $meta = $message->getMeta();
        $record = [
            'type'          => 'rcs',
            'id'            => $this->correlationId(),
            'phone'         => $this->normalizePhone($message->getRecipient()),
            'rcs_signature' => (string) $sender,
            'rcs_message'   => $message->getBody(),
            'hook'          => $this->getStatusCallbackUrl(),
        ];

        if (!empty($meta['media_url'])) {
            $record['rcs_image'] = (string) $meta['media_url'];
        }
        if (!empty($meta['link_url'])) {
            $record['rcs_link'] = (string) $meta['link_url'];
        }
        if (!empty($meta['button_text'])) {
            $record['rcs_button'] = (string) $meta['button_text'];
        }

        return $record;
    }

    private function postAndParse(string $apiKey, array $record): DeliveryResult
    {
        $result = $this->httpPost(self::API_URL, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode([
                'auth' => $apiKey,
                'data' => [$record],
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid AlphaSMS API key', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(sprintf(__('Invalid response from AlphaSMS (HTTP %d)', 'wp-sms'), $result['code']));
        }

        if (isset($data['success']) && $data['success'] === false) {
            return DeliveryResult::failed((string) ($data['error'] ?? __('AlphaSMS request failed', 'wp-sms')));
        }
        if (!empty($data['error'])) {
            return DeliveryResult::failed((string) $data['error']);
        }

        $first = $data['data'][0] ?? null;
        if (is_array($first) && !empty($first['error'])) {
            return DeliveryResult::failed((string) $first['error']);
        }

        $msgId = $first['data']['msg_id'] ?? null;
        return DeliveryResult::sent($msgId !== null ? (string) $msgId : null);
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $amount = $this->fetchBalance($apiKey);
        return $amount !== null ? (string) $amount : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_URL, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode([
                'auth' => $apiKey,
                'data' => [['type' => 'balance']],
            ]),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid AlphaSMS API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'AlphaSMS');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (isset($data['success']) && $data['success'] === false) {
            return TestConnectionResult::error((string) ($data['error'] ?? __('Authentication failed', 'wp-sms')));
        }
        if (!empty($data['error'])) {
            return TestConnectionResult::error((string) $data['error']);
        }

        $amount = $data['data'][0]['data']['amount'] ?? null;
        if ($amount === null) {
            return TestConnectionResult::ok(__('Connected to AlphaSMS', 'wp-sms'));
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s UAH', 'wp-sms'), $amount),
            ['balance' => (string) $amount],
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
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return false;
        }

        $token = $request->get_param('token');
        if (!is_string($token) || !hash_equals($this->callbackToken(), $token)) {
            return false;
        }

        // Optional defence-in-depth: AlphaSMS docs are silent on signing, but
        // if an X-Signature header is present we verify it as sha256(raw_body . api_key).
        // Not HMAC — string concatenation, per earlier provider research.
        $signature = $request->get_header('x-signature');
        if (is_string($signature) && $signature !== '') {
            $body = (string) ($request->get_body() ?? '');
            $expected = hash('sha256', $body . $apiKey);
            return hash_equals($expected, $signature);
        }

        return true;
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $payload = $request->get_json_params();
        $msgId = null;
        $statusRaw = null;

        if (is_array($payload)) {
            $msgId     = $payload['msg_id'] ?? ($payload['data'][0]['msg_id'] ?? null);
            $statusRaw = $payload['status'] ?? ($payload['data'][0]['status'] ?? null);
        }

        // Some DLR endpoints fire as form-encoded GET/POST; fall back to params.
        if ($msgId === null) {
            $msgId = $request->get_param('msg_id');
        }
        if ($statusRaw === null) {
            $statusRaw = $request->get_param('status');
        }

        if (empty($msgId)) {
            return [];
        }

        $upper = strtoupper((string) $statusRaw);
        $normalized = $this->normalizeDlrStatus($upper);

        return [new StatusUpdate(
            providerId:   (string) $msgId,
            status:       $normalized,
            errorCode:    $statusRaw !== null && $statusRaw !== '' ? (string) $statusRaw : null,
            errorMessage: $normalized === 'failed' ? sprintf('AlphaSMS: %s', $upper) : null,
            permanent:    $this->isPermanentDlrStatus($upper),
        )];
    }

    // --- Internal ---

    private function fetchBalance(string $apiKey): ?float
    {
        $result = $this->httpPost(self::API_URL, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode([
                'auth' => $apiKey,
                'data' => [['type' => 'balance']],
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return null;
        }

        $amount = $data['data'][0]['data']['amount'] ?? null;
        return $amount !== null ? (float) $amount : null;
    }

    private function callbackToken(): string
    {
        return hash_hmac('sha256', 'alphasms-callback', (string) $this->getSharedConfig('api_key'));
    }

    private function correlationId(): int
    {
        return random_int(1, PHP_INT_MAX);
    }

    private function normalizePhone(string $recipient): string
    {
        return preg_replace('/\D+/', '', $recipient) ?? '';
    }

    private function normalizeDlrStatus(string $upper): string
    {
        return match ($upper) {
            'DELIVERED', 'PARTIALLY DELIVERED', 'READ', 'REPLIED' => 'delivered',
            'ACCEPTED', 'QUEUED', 'ROUTING'                       => 'sent',
            'EXPIRED', 'REJECTED', 'UNDELIVERABLE', 'FILTERED',
            'NO ROUTE', 'SIM FULL', 'INVALID DESTINATION ADDRESS',
            'INVALID SOURCE ADDRESS', 'DELETED', 'UNKNOWN'        => 'failed',
            default                                                => strtolower($upper),
        };
    }

    private function isPermanentDlrStatus(string $upper): bool
    {
        return in_array($upper, [
            'REJECTED',
            'EXPIRED',
            'INVALID DESTINATION ADDRESS',
            'INVALID SOURCE ADDRESS',
            'NO ROUTE',
            'DELETED',
        ], true);
    }
}

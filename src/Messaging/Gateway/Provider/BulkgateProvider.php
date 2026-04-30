<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsInboundMessage;
use WSms\Messaging\Contracts\SupportsOptOutDetection;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

/**
 * BulkGate — multichannel via the unified /api/2.0/advanced/transactional endpoint.
 *
 * SMS, Viber, RCS, and WhatsApp share one POST endpoint with a `channel` block
 * routing the payload. Auth is body-based (application_id + application_token);
 * BulkGate offers no header-based auth scheme. Credit balance lives on the
 * legacy /api/1.0/simple/info endpoint.
 *
 * Webhook delivery reports and inbound MO messages share a single account-level
 * URL configured in the BulkGate portal. The payload is a JSON array of entries
 * with a `status` field — 1/2/3 are DLR statuses, 10 is an inbound MO, 13 is
 * a Viber "seen" event. BulkGate does not sign webhooks; we require a
 * shared-secret query token on the URL instead.
 */
class BulkgateProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsOptOutDetection
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = true;

    private const SEND_URL   = 'https://portal.bulkgate.com/api/2.0/advanced/transactional';
    private const INFO_URL   = 'https://portal.bulkgate.com/api/1.0/simple/info';
    private const APP_PRODUCT = 'wsms';

    public function getId(): string
    {
        return 'bulkgate';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'viber', 'rcs', 'whatsapp'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'application_id' => [
                    'type'        => 'string',
                    'label'       => __('Application ID', 'wp-sms'),
                    'required'    => true,
                    'description' => __('From the BulkGate portal under Settings > API Applications.', 'wp-sms'),
                ],
                'application_token' => [
                    'type'        => 'secret',
                    'label'       => __('Application Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Application token paired with the Application ID above.', 'wp-sms'),
                ],
                'callback_token' => [
                    'type'        => 'secret',
                    'label'       => __('Callback Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Shared secret appended to the webhook URL as ?token=… so the plugin can verify delivery reports and inbound messages. Required to enable two-way SMS and DLR processing — BulkGate does not sign webhooks.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'    => 'select',
                        'label'   => __('Sender ID Type', 'wp-sms'),
                        'default' => 'gSystem',
                        'options' => [
                            ['value' => 'gSystem',  'label' => __('System Number', 'wp-sms')],
                            ['value' => 'gShort',   'label' => __('Short Code', 'wp-sms')],
                            ['value' => 'gText',    'label' => __('Text Sender', 'wp-sms')],
                            ['value' => 'gOwn',     'label' => __('Own Number', 'wp-sms')],
                            ['value' => 'gProfile', 'label' => __('BulkGate Profile ID', 'wp-sms')],
                            ['value' => 'gMobile',  'label' => __('Mobile Connect', 'wp-sms')],
                            ['value' => 'gPush',    'label' => __('Mobile Connect Push', 'wp-sms')],
                        ],
                        'description' => __('Pick the sender type that matches what is provisioned on your account. System Number works for every account out of the box.', 'wp-sms'),
                    ],
                    'sender_id_value' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID Value', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Value paired with the sender type — e.g. "MyBrand" for Text Sender, "+420777123456" for Own Number, profile ID for Profile.', 'wp-sms'),
                    ],
                    'unicode' => [
                        'type'        => 'boolean',
                        'label'       => __('Unicode (UCS-2)', 'wp-sms'),
                        'default'     => false,
                        'description' => __('Enable for messages containing non-GSM characters (emoji, Cyrillic, Arabic, etc.).', 'wp-sms'),
                    ],
                ],
                'viber' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Viber Sender', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Registered Viber Business sender name. Must be approved by BulkGate before use.', 'wp-sms'),
                    ],
                    'expiration' => [
                        'type'        => 'number',
                        'label'       => __('Viber Expiration (seconds)', 'wp-sms'),
                        'default'     => 120,
                        'description' => __('How long BulkGate waits for Viber delivery before falling back to the SMS channel.', 'wp-sms'),
                    ],
                ],
                'rcs' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('RCS Sender', 'wp-sms'),
                        'required'    => true,
                        'description' => __('RCS agent / brand sender ID provisioned with BulkGate. RCS delivery is currently limited to CZ, SK and DE.', 'wp-sms'),
                    ],
                    'expiration' => [
                        'type'        => 'number',
                        'label'       => __('RCS Expiration (seconds)', 'wp-sms'),
                        'default'     => 120,
                        'description' => __('How long BulkGate waits for RCS delivery before falling back to the SMS channel.', 'wp-sms'),
                    ],
                ],
                'whatsapp' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('WhatsApp Sender', 'wp-sms'),
                        'required'    => true,
                        'description' => __('WhatsApp Business sender phone in E.164 (e.g. 420777123456). Requires Meta Business onboarding via BulkGate.', 'wp-sms'),
                        'placeholder' => '420777123456',
                    ],
                    'expiration' => [
                        'type'        => 'number',
                        'label'       => __('WhatsApp Expiration (seconds)', 'wp-sms'),
                        'default'     => 120,
                        'description' => __('How long BulkGate waits for WhatsApp delivery before falling back to the SMS channel.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'delivery_receipt' => true,
            'incoming'         => true,
            'unicode'          => true,
            'test_connection'  => true,
        ]);
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $appId    = $this->getSharedConfig('application_id');
        $appToken = $this->getSharedConfig('application_token');

        if (!$appId || !$appToken) {
            return DeliveryResult::failed(__('BulkGate credentials not configured', 'wp-sms'));
        }

        $channel = $message->getChannel();

        $body = [
            'application_id'      => $appId,
            'application_token'   => $appToken,
            'application_product' => self::APP_PRODUCT,
            'number'              => $message->getRecipient(),
            'text'                => $message->getBody(),
            'duplicates_check'    => false,
        ];

        $channelBlock = $this->buildChannelBlock($channel, $message->getBody());
        if ($channelBlock instanceof DeliveryResult) {
            return $channelBlock;
        }
        $body['channel'] = [$channel => $channelBlock];

        $result = $this->httpPost(self::SEND_URL, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid BulkGate Application ID or Application Token', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300 && is_array($data) && isset($data['data'])) {
            return DeliveryResult::queued($this->extractProviderId($data['data']));
        }

        $errorMessage = $this->describeError($data, $result['code']);
        $errorType    = is_array($data) ? ($data['type'] ?? null) : null;
        $errorCode    = is_array($data) ? ($data['code'] ?? null) : null;

        return DeliveryResult::failed(
            $errorMessage,
            meta: array_filter([
                'bulkgate_error_type' => $errorType,
                'bulkgate_error_code' => $errorCode !== null ? (string) $errorCode : null,
            ]),
        );
    }

    public function getCredit(): ?string
    {
        $data = $this->fetchInfo();
        if ($data === null) {
            return null;
        }

        $credit   = $data['credit'] ?? null;
        $currency = $data['currency'] ?? 'credits';
        if ($credit === null) {
            return null;
        }

        return number_format((float) $credit, 4) . ' ' . $currency;
    }

    public function testConnection(): TestConnectionResult
    {
        $appId    = $this->getSharedConfig('application_id');
        $appToken = $this->getSharedConfig('application_token');

        if (!$appId || !$appToken) {
            return TestConnectionResult::error(__('Application ID and Application Token are required', 'wp-sms'));
        }

        $result = $this->postInfo($appId, $appToken);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Application ID or Application Token', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'BulkGate');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $info = $data['data'] ?? null;
        if (!is_array($info) || !isset($info['credit'])) {
            return TestConnectionResult::error(__('Unexpected response from BulkGate', 'wp-sms'));
        }

        $credit   = $info['credit'];
        $currency = $info['currency'] ?? 'credits';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s %s', 'wp-sms'), $credit, $currency),
            ['balance' => (string) $credit, 'currency' => (string) $currency],
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $updates = [];
        foreach ($this->extractEntries($request) as $entry) {
            $status = (string) ($entry['status'] ?? '');
            // Skip MO (10) and Viber-seen (13) — handled by parseInboundCallback or ignored.
            if (!in_array($status, ['1', '2', '3'], true)) {
                continue;
            }

            $smsId = $entry['smsID'] ?? null;
            if (!$smsId) {
                continue;
            }

            $normalized = match ($status) {
                '1'     => 'delivered',
                '2'     => 'queued',
                '3'     => 'failed',
            };

            $updates[] = new StatusUpdate(
                providerId:   (string) $smsId,
                status:       $normalized,
                errorCode:    $status === '3' ? '3' : null,
                errorMessage: $status === '3' ? __('BulkGate: unknown or unavailable recipient', 'wp-sms') : null,
                permanent:    $status === '3',
            );
        }

        return $updates;
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $messages = [];
        foreach ($this->extractEntries($request) as $entry) {
            if ((string) ($entry['status'] ?? '') !== '10') {
                continue;
            }

            $from = (string) ($entry['from'] ?? '');
            if ($from === '') {
                continue;
            }

            $messages[] = new InboundMessage(
                from:       $from,
                to:         (string) ($entry['to'] ?? ''),
                body:       (string) ($entry['message'] ?? ''),
                providerId: isset($entry['smsID']) ? (string) $entry['smsID'] : null,
                meta:       array_filter([
                    'date'    => $entry['date'] ?? null,
                    'channel' => $entry['channel'] ?? null,
                ]),
            );
        }

        return $messages;
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        return ($result->meta['bulkgate_error_type'] ?? null) === 'blacklisted_number';
    }

    // --- Internal ---

    /**
     * Build the per-channel block. Returns DeliveryResult on missing required config.
     *
     * @return array|DeliveryResult
     */
    private function buildChannelBlock(string $channel, string $body): array|DeliveryResult
    {
        switch ($channel) {
            case 'sms':
                $block = [
                    'sender_id' => $this->getChannelConfig('sms', 'sender_id', 'gSystem'),
                    'unicode'   => (bool) $this->getChannelConfig('sms', 'unicode', false),
                ];
                $senderValue = $this->getChannelConfig('sms', 'sender_id_value');
                if ($senderValue !== null && $senderValue !== '') {
                    $block['sender_id_value'] = (string) $senderValue;
                }
                return $block;

            case 'viber':
            case 'rcs':
            case 'whatsapp':
                $sender = $this->getChannelConfig($channel, 'sender');
                if (!$sender) {
                    return DeliveryResult::failed(sprintf(
                        /* translators: %s: BulkGate channel name (Viber, RCS, WhatsApp) */
                        __('BulkGate %s sender is not configured', 'wp-sms'),
                        strtoupper($channel),
                    ));
                }

                $expiration = $this->getChannelConfig($channel, 'expiration', 120);
                $block = [
                    'sender'     => (string) $sender,
                    'expiration' => (int) $expiration,
                ];

                if ($channel === 'viber') {
                    // Viber uses a flat `text` field on the channel block per the v2 spec.
                    $block['text'] = $body;
                } else {
                    // RCS and WhatsApp wrap text inside a `message` object.
                    $block['message'] = ['text' => $body];
                }

                return $block;
        }

        return DeliveryResult::failed(sprintf(
            /* translators: %s: channel slug supplied by the caller */
            __('BulkGate does not support channel %s', 'wp-sms'),
            $channel,
        ));
    }

    private function extractProviderId(array $data): ?string
    {
        // Single-recipient response carries `data.id` directly.
        if (isset($data['id'])) {
            return (string) $data['id'];
        }

        // Multi-recipient response (or some channel responses) wrap the IDs in `data.response[]`.
        $response = $data['response'] ?? null;
        if (is_array($response) && !empty($response)) {
            $first = $response[0];
            if (is_array($first) && isset($first['id'])) {
                return (string) $first['id'];
            }
        }

        return null;
    }

    private function describeError(mixed $data, int $httpCode): string
    {
        if (is_array($data)) {
            if (!empty($data['error']) && is_string($data['error'])) {
                return $data['error'];
            }
            if (!empty($data['type']) && is_string($data['type'])) {
                return sprintf('BulkGate: %s', $data['type']);
            }
        }
        return sprintf('HTTP %d', $httpCode);
    }

    /**
     * @return array{response: array, body: string, code: int}|DeliveryResult
     */
    private function postInfo(string $appId, string $appToken): array|DeliveryResult
    {
        return $this->httpPost(self::INFO_URL, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'application_id'      => $appId,
                'application_token'   => $appToken,
                'application_product' => self::APP_PRODUCT,
            ]),
        ]);
    }

    /**
     * Fetch and decode /simple/info, returning the inner `data` array on success.
     */
    private function fetchInfo(): ?array
    {
        $appId    = $this->getSharedConfig('application_id');
        $appToken = $this->getSharedConfig('application_token');

        if (!$appId || !$appToken) {
            return null;
        }

        $result = $this->postInfo($appId, $appToken);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $decoded = json_decode($result['body'], true);
        $info = $decoded['data'] ?? null;
        return is_array($info) ? $info : null;
    }

    private function verifyToken(\WP_REST_Request $request): bool
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

    /**
     * BulkGate posts a top-level JSON array of DLR/MO entries. Tolerate both
     * the top-level array and the rare wrapped {entries: [...]} shape.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractEntries(\WP_REST_Request $request): array
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            return [];
        }

        // Top-level array of entries (canonical BulkGate shape).
        if (array_is_list($payload)) {
            return array_filter($payload, 'is_array');
        }

        // Wrapped variant — pluck a list-shaped child if present.
        foreach ($payload as $value) {
            if (is_array($value) && array_is_list($value)) {
                return array_filter($value, 'is_array');
            }
        }

        return [];
    }
}

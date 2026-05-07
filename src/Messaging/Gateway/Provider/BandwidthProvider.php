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

class BandwidthProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsDynamicOptions,
    SupportsOptOutDetection
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const MESSAGING_BASE = 'https://messaging.bandwidth.com/api/v2/users';
    private const NUMBERS_BASE = 'https://api.bandwidth.com/api';

    // TODO(verify): Bandwidth has MFA /code/messaging + /code/verify endpoints; defer until SupportsVerify lands.
    // TODO(voice): Bandwidth Voice is a separate API; add when WSMS exposes a voice channel.

    public function getId(): string
    {
        return 'bandwidth';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'rcs'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'account_id' => [
                    'type'        => 'string',
                    'label'       => __('Account ID', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Bandwidth account ID from the Bandwidth App account overview.', 'wp-sms'),
                    'placeholder' => '9900000',
                ],
                'api_token' => [
                    'type'        => 'string',
                    'label'       => __('API Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Messaging API token used as the Basic Auth username.', 'wp-sms'),
                ],
                'api_secret' => [
                    'type'        => 'secret',
                    'label'       => __('API Secret', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Messaging API secret used as the Basic Auth password.', 'wp-sms'),
                ],
                'callback_username' => [
                    'type'        => 'string',
                    'label'       => __('Callback Username', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Username configured in the Bandwidth App for inbound and status callback basic authentication. Leave blank to reject callbacks.', 'wp-sms'),
                ],
                'callback_password' => [
                    'type'        => 'secret',
                    'label'       => __('Callback Password', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Password configured in the Bandwidth App for inbound and status callback basic authentication. Leave blank to reject callbacks.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from_number' => [
                        'type'        => 'string',
                        'label'       => __('From Number', 'wp-sms'),
                        'required'    => true,
                        'dynamic'     => true,
                        'description' => __('Bandwidth SMS/MMS-enabled number in E.164 format.', 'wp-sms'),
                        'placeholder' => '+19195551212',
                    ],
                    'application_id' => [
                        'type'        => 'string',
                        'label'       => __('Application ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Messaging Application ID associated with the From Number in the Bandwidth App.', 'wp-sms'),
                        'placeholder' => '93de2206-9669-4e07-948d-329f4b722ee2',
                    ],
                ],
                'rcs' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('RCS Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Approved RBM/RCS sender ID associated with your Bandwidth messaging application.', 'wp-sms'),
                        'placeholder' => 'MyBrand',
                    ],
                    'application_id' => [
                        'type'        => 'string',
                        'label'       => __('Application ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Messaging Application ID associated with the RCS sender in the Bandwidth App.', 'wp-sms'),
                        'placeholder' => '93de2206-9669-4e07-948d-329f4b722ee2',
                    ],
                    'sms_fallback_from' => [
                        'type'        => 'string',
                        'label'       => __('SMS Fallback From Number', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Optional Bandwidth SMS number. If set, Bandwidth will try SMS after the RBM/RCS channel fails.', 'wp-sms'),
                        'placeholder' => '+19195551212',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $accountId = $this->getSharedConfig('account_id');
        $apiToken = $this->getSharedConfig('api_token');
        $apiSecret = $this->getSharedConfig('api_secret');

        if (!$accountId || !$apiToken || !$apiSecret) {
            return DeliveryResult::failed(__('Bandwidth credentials not configured', 'wp-sms'));
        }

        return match ($message->getChannel()) {
            'sms'   => $this->sendSms($message, (string) $accountId, (string) $apiToken, (string) $apiSecret),
            'rcs'   => $this->sendRcs($message, (string) $accountId, (string) $apiToken, (string) $apiSecret),
            default => DeliveryResult::failed(sprintf(__('Bandwidth does not support channel %s', 'wp-sms'), $message->getChannel())),
        };
    }

    private function sendSms(MessageInterface $message, string $accountId, string $apiToken, string $apiSecret): DeliveryResult
    {
        $from = $this->getChannelConfig('sms', 'from_number');
        $applicationId = $this->getChannelConfig('sms', 'application_id');

        if (!$from || !$applicationId) {
            return DeliveryResult::failed(__('Bandwidth From Number and Application ID are required for SMS', 'wp-sms'));
        }

        $meta = $message->getMeta();
        $body = [
            'to'            => [$message->getRecipient()],
            'from'          => $from,
            'text'          => $message->getBody(),
            'applicationId' => $applicationId,
        ];

        if (!empty($meta['media_urls'])) {
            $body['media'] = array_values((array) $meta['media_urls']);
        }
        if (!empty($meta['tag'])) {
            $body['tag'] = (string) $meta['tag'];
        }
        if (!empty($meta['priority']) && in_array($meta['priority'], ['default', 'high'], true)) {
            $body['priority'] = (string) $meta['priority'];
        }

        $result = $this->httpPost($this->messagingUrl($accountId, '/messages'), [
            'headers' => $this->authHeaders($apiToken, $apiSecret),
            'body'    => wp_json_encode($body),
        ]);

        return $this->parseSendResponse($result, 'message');
    }

    private function sendRcs(MessageInterface $message, string $accountId, string $apiToken, string $apiSecret): DeliveryResult
    {
        $senderId = $this->getChannelConfig('rcs', 'sender_id');
        $applicationId = $this->getChannelConfig('rcs', 'application_id');

        if (!$senderId || !$applicationId) {
            return DeliveryResult::failed(__('Bandwidth RCS Sender ID and Application ID are required', 'wp-sms'));
        }

        $meta = $message->getMeta();
        $rbmContent = ['text' => $message->getBody()];
        if (!empty($meta['media_urls'])) {
            $rbmContent['media'] = array_map(
                fn($url) => ['fileUrl' => (string) $url],
                array_values((array) $meta['media_urls']),
            );
        }

        $channelList = [[
            'from'          => $senderId,
            'applicationId' => $applicationId,
            'channel'       => 'RBM',
            'content'       => $rbmContent,
        ]];

        $fallbackFrom = $this->getChannelConfig('rcs', 'sms_fallback_from');
        if ($fallbackFrom) {
            $channelList[] = [
                'from'          => $fallbackFrom,
                'applicationId' => $applicationId,
                'channel'       => 'SMS',
                'content'       => ['text' => $message->getBody()],
            ];
        }

        $body = [
            'to'          => $message->getRecipient(),
            'channelList' => $channelList,
        ];

        if (!empty($meta['tag'])) {
            $body['tag'] = (string) $meta['tag'];
        }
        if (!empty($meta['priority']) && in_array($meta['priority'], ['default', 'high'], true)) {
            $body['priority'] = (string) $meta['priority'];
        }
        if (!empty($meta['expiration'])) {
            $body['expiration'] = (string) $meta['expiration'];
        }

        $result = $this->httpPost($this->messagingUrl($accountId, '/messages/multiChannel'), [
            'headers' => $this->authHeaders($apiToken, $apiSecret),
            'body'    => wp_json_encode($body),
        ]);

        return $this->parseSendResponse($result, 'multi_channel');
    }

    public function getCredit(): ?string
    {
        // Bandwidth's public Messaging and Number Management docs do not expose a prepaid balance endpoint.
        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        $accountId = $this->getSharedConfig('account_id');
        $apiToken = $this->getSharedConfig('api_token');
        $apiSecret = $this->getSharedConfig('api_secret');

        if (!$accountId || !$apiToken || !$apiSecret) {
            return TestConnectionResult::error(__('Account ID, API Token, and API Secret are required', 'wp-sms'));
        }

        $result = $this->httpGet($this->messagingUrl((string) $accountId, '/messages?size=1'), [
            'headers' => $this->authHeaders((string) $apiToken, (string) $apiSecret),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401) {
                return TestConnectionResult::error(__('Invalid Bandwidth API Token or API Secret', 'wp-sms'));
            }
            if ($result['code'] === 403) {
                return TestConnectionResult::error(__('Bandwidth credentials do not have access to the Messaging API', 'wp-sms'));
            }
            if ($result['code'] === 404) {
                return TestConnectionResult::error(__('Bandwidth account not found — check your Account ID', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Bandwidth');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        return TestConnectionResult::ok(__('Connected to Bandwidth Messaging API', 'wp-sms'), [
            'account_id' => $accountId,
        ]);
    }

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->validateCallbackBasicAuth($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $updates = [];
        foreach ($this->callbackEvents($request) as $event) {
            $type = (string) ($event['type'] ?? '');
            if (!in_array($type, ['message-sending', 'message-delivered', 'message-failed', 'message-read'], true)) {
                continue;
            }

            $message = $event['message'] ?? [];
            $messageId = $message['id'] ?? null;
            if (!$messageId) {
                continue;
            }

            $errorCode = isset($event['errorCode']) ? (string) $event['errorCode'] : null;
            $updates[] = new StatusUpdate(
                providerId:   (string) $messageId,
                status:       $this->normalizeStatus($type),
                errorCode:    $errorCode,
                errorMessage: $type === 'message-failed' ? (string) ($event['description'] ?? 'Bandwidth delivery failed') : null,
                permanent:    $this->isPermanentErrorCode($errorCode),
                unsubscribe:  $errorCode === '4475',
            );
        }

        return $updates;
    }

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->validateCallbackBasicAuth($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $messages = [];
        foreach ($this->callbackEvents($request) as $event) {
            if (($event['type'] ?? '') !== 'message-received') {
                continue;
            }

            $message = $event['message'] ?? [];
            $from = $message['from'] ?? null;
            if (!$from) {
                continue;
            }

            $to = $message['to'] ?? ($event['to'] ?? '');
            if (is_array($to)) {
                $to = $to[0] ?? '';
            }

            $body = (string) ($message['text'] ?? ($message['content']['text'] ?? ''));
            $messages[] = new InboundMessage(
                from:       (string) $from,
                to:         (string) $to,
                body:       $body,
                providerId: isset($message['id']) ? (string) $message['id'] : null,
                meta:       array_filter([
                    'channel'       => $message['channel'] ?? null,
                    'applicationId' => $message['applicationId'] ?? null,
                    'media_urls'    => $message['media'] ?? null,
                    'tag'           => $message['tag'] ?? null,
                ]),
            );
        }

        return $messages;
    }

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'from_number' || $section !== 'sms') {
            return [];
        }

        return $this->withConfig($config, function () {
            $apiToken = $this->getSharedConfig('api_token');
            $apiSecret = $this->getSharedConfig('api_secret');
            if (!$apiToken || !$apiSecret) {
                return [];
            }

            $result = $this->httpGet(self::NUMBERS_BASE . '/tns', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode("{$apiToken}:{$apiSecret}"),
                    'Accept'        => 'application/xml',
                ],
            ]);

            if ($result instanceof DeliveryResult) {
                return [];
            }
            if ($result['code'] === 401 || $result['code'] === 403) {
                throw new \RuntimeException(__('Invalid Bandwidth API Token or API Secret', 'wp-sms'));
            }
            if ($result['code'] < 200 || $result['code'] >= 300) {
                throw new \RuntimeException(sprintf(__('Unexpected response from Bandwidth Numbers API (HTTP %d)', 'wp-sms'), $result['code']));
            }

            return $this->parseNumberOptions($result['body']);
        });
    }

    public function isOptOutError(DeliveryResult $result): bool
    {
        return ($result->meta['bandwidth_error_code'] ?? null) === '4475';
    }

    private function parseSendResponse(array|DeliveryResult $result, string $mode): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        $data = is_array($data) ? $data : [];

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Bandwidth credentials', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300) {
            $providerId = $mode === 'multi_channel'
                ? ($data['data']['id'] ?? $data['id'] ?? null)
                : ($data['id'] ?? null);

            return DeliveryResult::queued($providerId ? (string) $providerId : null);
        }

        $error = $this->extractErrorMessage($data, $result['code']);
        $errorCode = $this->extractErrorCode($data);

        return DeliveryResult::failed(
            $error,
            meta: array_filter([
                'bandwidth_error_code' => $errorCode,
                'bandwidth_error_type' => $data['type'] ?? null,
                'bandwidth_http_code'  => $result['code'] ?: null,
            ]),
            retryable: $result['code'] === 429 || $result['code'] >= 500,
        );
    }

    private function authHeaders(string $apiToken, string $apiSecret): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode("{$apiToken}:{$apiSecret}"),
            'Content-Type'  => 'application/json; charset=utf-8',
            'Accept'        => 'application/json',
        ];
    }

    private function messagingUrl(string $accountId, string $path): string
    {
        return self::MESSAGING_BASE . '/' . rawurlencode($accountId) . $path;
    }

    private function validateCallbackBasicAuth(\WP_REST_Request $request): bool
    {
        $username = $this->getSharedConfig('callback_username');
        $password = $this->getSharedConfig('callback_password');
        if (!$username || !$password) {
            return false;
        }

        $header = (string) ($request->get_header('authorization') ?? '');
        if (!str_starts_with($header, 'Basic ')) {
            return false;
        }

        $decoded = base64_decode(substr($header, 6), true);
        if (!is_string($decoded) || !str_contains($decoded, ':')) {
            return false;
        }

        [$candidateUser, $candidatePass] = explode(':', $decoded, 2);
        return hash_equals((string) $username, $candidateUser)
            && hash_equals((string) $password, $candidatePass);
    }

    /** @return array<int, array<string, mixed>> */
    private function callbackEvents(\WP_REST_Request $request): array
    {
        $events = $request->get_json_params();
        if (!is_array($events)) {
            return [];
        }

        $isList = array_keys($events) === range(0, count($events) - 1);
        return $isList ? $events : [$events];
    }

    private function normalizeStatus(string $type): string
    {
        return match ($type) {
            'message-sending'   => 'sent',
            'message-delivered', 'message-read' => 'delivered',
            'message-failed'    => 'failed',
            default             => $type,
        };
    }

    private function isPermanentErrorCode(?string $code): bool
    {
        if ($code === null || $code === '') {
            return false;
        }

        return str_starts_with($code, '4');
    }

    private function extractErrorMessage(array $data, int $statusCode): string
    {
        if (!empty($data['description'])) {
            return (string) $data['description'];
        }
        if (!empty($data['message'])) {
            return (string) $data['message'];
        }
        if (!empty($data['errors']) && is_array($data['errors'])) {
            $first = reset($data['errors']);
            if (is_array($first)) {
                return (string) ($first['description'] ?? $first['message'] ?? $first['code'] ?? "HTTP {$statusCode}");
            }
            if (is_string($first) && $first !== '') {
                return $first;
            }
        }

        return "HTTP {$statusCode}";
    }

    private function extractErrorCode(array $data): ?string
    {
        if (isset($data['errorCode'])) {
            return (string) $data['errorCode'];
        }
        if (!empty($data['errors']) && is_array($data['errors'])) {
            $first = reset($data['errors']);
            if (is_array($first) && isset($first['code'])) {
                return (string) $first['code'];
            }
        }

        return null;
    }

    /** @return array<array{value: string, label: string}> */
    private function parseNumberOptions(string $xmlBody): array
    {
        if (!function_exists('simplexml_load_string')) {
            return [];
        }

        $xml = @simplexml_load_string($xmlBody);
        if (!$xml) {
            return [];
        }

        $options = [];
        foreach ($xml->xpath('//TelephoneNumber') ?: [] as $numberNode) {
            $number = trim((string) $numberNode);
            if ($number === '') {
                continue;
            }

            $value = '+' . ltrim($number, '+');
            $options[] = ['value' => $value, 'label' => $value];
        }

        return $options;
    }
}

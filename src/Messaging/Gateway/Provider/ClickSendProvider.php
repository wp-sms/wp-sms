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
 * ClickSend SMS/MMS provider.
 *
 * RCS Beta uses ClickSend's normal SMS endpoints. ClickSend decides whether a
 * message can be delivered as RCS and falls back to SMS when needed.
 *
 * TODO(voice): ClickSend supports voice; defer until WSMS adds voice channel support.
 */
class ClickSendProvider extends AbstractProvider implements SupportsStatusCallback, SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://rest.clicksend.com';
    private const SOURCE = 'wp-sms';

    public function getId(): string
    {
        return 'clicksend';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'rcs'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'username' => [
                    'type'        => 'string',
                    'label'       => __('Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your ClickSend API username from the dashboard API Credentials page.', 'wp-sms'),
                ],
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your ClickSend API key. It is used as the HTTP Basic password.', 'wp-sms'),
                ],
                'webhook_token' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Optional token to add as ?token=... to ClickSend delivery report and inbound webhook URLs.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Optional ClickSend sender ID, dedicated number, shared number, alpha tag, or own number. Leave blank to use ClickSend Smart Sender defaults.', 'wp-sms'),
                        'placeholder' => '+15551234567',
                    ],
                ],
                'rcs' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Fallback Sender ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Optional SMS fallback sender. RCS Beta still uses the SMS endpoint; ClickSend controls RCS eligibility and SMS fallback.', 'wp-sms'),
                        'placeholder' => '+15551234567',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = $this->getSharedConfig('username');
        $apiKey = $this->getSharedConfig('api_key');

        if (!$username || !$apiKey) {
            return DeliveryResult::failed(__('ClickSend credentials not configured', 'wp-sms'));
        }

        $channel = $message->getChannel();
        if (!in_array($channel, $this->getSupportedChannels(), true)) {
            return DeliveryResult::failed(sprintf(__('ClickSend does not support channel %s', 'wp-sms'), $channel));
        }

        $meta = $message->getMeta();
        $mediaUrls = $meta['media_urls'] ?? [];

        if (!empty($mediaUrls)) {
            return $this->sendMms($message, (string) $mediaUrls[0]);
        }

        return $this->sendSms($message);
    }

    public function getCredit(): ?string
    {
        if (!$this->hasCredentials()) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/v3/account', [
            'headers' => $this->authHeaders(),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        $account = is_array($data) ? ($data['data'] ?? []) : [];
        $balance = $account['balance'] ?? null;

        if ($balance === null) {
            return null;
        }

        $currency = $account['_currency']['currency_name_short'] ?? null;

        return trim((string) $balance . ($currency ? ' ' . $currency : ''));
    }

    public function testConnection(): TestConnectionResult
    {
        if (!$this->getSharedConfig('username') || !$this->getSharedConfig('api_key')) {
            return TestConnectionResult::error(__('Username and API Key are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/v3/account', [
            'headers' => $this->authHeaders(),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid ClickSend username or API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'ClickSend');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (($data['response_code'] ?? null) !== 'SUCCESS') {
            return TestConnectionResult::error($data['response_msg'] ?? __('ClickSend rejected the account request', 'wp-sms'));
        }

        $account = $data['data'] ?? [];
        $balance = $account['balance'] ?? 'N/A';
        $currency = $account['_currency']['currency_name_short'] ?? null;
        $balanceLabel = trim((string) $balance . ($currency ? ' ' . $currency : ''));

        return TestConnectionResult::ok(
            sprintf(__('Connected to ClickSend - Balance: %s', 'wp-sms'), $balanceLabel),
            array_filter(['balance' => $balance, 'currency' => $currency]),
        );
    }

    public function getStatusCallbackUrl(): string
    {
        return $this->callbackUrl('status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->validateWebhookToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $payload = $this->payload($request);
        $messageId = (string) ($payload['message_id'] ?? '');

        if ($messageId === '') {
            return [];
        }

        $statusCode = isset($payload['status_code']) ? (string) $payload['status_code'] : null;
        $statusText = isset($payload['status_text']) ? (string) $payload['status_text'] : '';
        $errorCode = isset($payload['error_code']) && $payload['error_code'] !== ''
            ? (string) $payload['error_code']
            : null;
        $errorText = isset($payload['error_text']) && $payload['error_text'] !== ''
            ? (string) $payload['error_text']
            : null;

        $status = $this->normalizeDeliveryStatus(
            (string) ($payload['status'] ?? ''),
            $statusCode,
            $statusText,
            $errorCode,
        );

        return [new StatusUpdate(
            providerId: $messageId,
            status: $status,
            errorCode: $errorCode ?? $statusCode,
            errorMessage: $errorText ?: ($status === 'failed' ? $statusText : null),
            permanent: $status === 'failed' && $statusCode !== '300',
        )];
    }

    public function getInboundCallbackUrl(): string
    {
        return $this->callbackUrl('inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->validateWebhookToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $payload = $this->payload($request);
        $from = (string) ($payload['from'] ?? '');

        if ($from === '') {
            return [];
        }

        return [new InboundMessage(
            from: $from,
            to: (string) ($payload['to'] ?? ''),
            body: (string) ($payload['body'] ?? $payload['message'] ?? ''),
            providerId: isset($payload['message_id']) ? (string) $payload['message_id'] : null,
            meta: array_filter([
                'original_message_id' => $payload['original_message_id'] ?? null,
                'timestamp'           => $payload['timestamp'] ?? null,
            ]),
        )];
    }

    private function sendSms(MessageInterface $message): DeliveryResult
    {
        $channel = $message->getChannel();
        $messageBody = [
            'source' => self::SOURCE,
            'body'   => $message->getBody(),
            'to'     => $message->getRecipient(),
        ];

        $from = $this->getChannelConfig($channel, 'from');
        if ($from) {
            $messageBody['from'] = $from;
        }

        return $this->postMessage(self::API_BASE . '/v3/sms/send', [
            'messages' => [$messageBody],
        ], 'ClickSend SMS');
    }

    private function sendMms(MessageInterface $message, string $mediaUrl): DeliveryResult
    {
        $channel = $message->getChannel();
        $from = $this->getChannelConfig($channel, 'from') ?: $this->getChannelConfig('sms', 'from');

        if (!$from) {
            return DeliveryResult::failed(__('ClickSend MMS requires a Sender ID because the MMS endpoint requires from.', 'wp-sms'));
        }

        $subject = (string) ($message->getMeta()['subject'] ?? __('Message', 'wp-sms'));

        return $this->postMessage(self::API_BASE . '/v3/mms/send', [
            'media_file' => $mediaUrl,
            'messages'   => [[
                'source'  => self::SOURCE,
                'subject' => substr($subject, 0, 20),
                'from'    => $from,
                'body'    => $message->getBody(),
                'to'      => $message->getRecipient(),
            ]],
        ], 'ClickSend MMS');
    }

    private function postMessage(string $url, array $body, string $label): DeliveryResult
    {
        $result = $this->httpPost($url, [
            'headers' => array_merge($this->authHeaders(), [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ]),
            'body' => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(sprintf(__('Invalid response from %s', 'wp-sms'), $label));
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid ClickSend username or API key', 'wp-sms'));
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed($data['response_msg'] ?? $data['message'] ?? sprintf('HTTP %d', $result['code']));
        }

        if (($data['response_code'] ?? null) !== 'SUCCESS') {
            return DeliveryResult::failed($data['response_msg'] ?? (string) ($data['response_code'] ?? __('ClickSend request failed', 'wp-sms')));
        }

        $message = $data['data']['messages'][0] ?? [];
        $messageStatus = (string) ($message['status'] ?? '');
        $blockedCount = (int) ($data['data']['blocked_count'] ?? 0);

        if ($blockedCount > 0 || ($messageStatus !== '' && $messageStatus !== 'SUCCESS')) {
            return DeliveryResult::failed(
                $message['status_text']
                    ?? $message['status']
                    ?? $data['response_msg']
                    ?? __('ClickSend did not queue the message', 'wp-sms'),
                meta: array_filter([
                    'clicksend_status' => $messageStatus ?: null,
                    'blocked_count'    => $blockedCount ?: null,
                    'error_code'       => $message['error_code'] ?? null,
                ]),
            );
        }

        $providerId = $message['message_id'] ?? null;
        $cost = isset($message['message_price']) ? (float) $message['message_price'] : null;

        return DeliveryResult::queued(is_string($providerId) ? $providerId : null, $cost);
    }

    private function hasCredentials(): bool
    {
        return (bool) ($this->getSharedConfig('username') && $this->getSharedConfig('api_key'));
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode($this->getSharedConfig('username') . ':' . $this->getSharedConfig('api_key')),
        ];
    }

    private function validateWebhookToken(\WP_REST_Request $request): bool
    {
        $token = $this->getSharedConfig('webhook_token');
        if (!$token) {
            return true;
        }

        $received = $request->get_param('token');
        return is_string($received) && hash_equals((string) $token, $received);
    }

    private function callbackUrl(string $type): string
    {
        $url = RestRoute::url('callbacks/' . $this->getId() . '/' . $type);
        $token = $this->getSharedConfig('webhook_token');

        if (!$token) {
            return $url;
        }

        return $url . '?token=' . rawurlencode((string) $token);
    }

    private function payload(\WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        return !empty($json) ? $json : $request->get_params();
    }

    private function normalizeDeliveryStatus(string $rawStatus, ?string $statusCode, string $statusText, ?string $errorCode): string
    {
        $raw = strtolower(trim($rawStatus));
        if ($raw !== '') {
            if (str_contains($raw, 'deliver')) {
                return 'delivered';
            }
            if (str_contains($raw, 'fail') || str_contains($raw, 'undeliver') || str_contains($raw, 'cancel')) {
                return 'failed';
            }
            if (str_contains($raw, 'queue') || str_contains($raw, 'sched') || str_contains($raw, 'approval')) {
                return 'queued';
            }
            if (str_contains($raw, 'sent') || str_contains($raw, 'complete')) {
                return 'sent';
            }
        }

        $text = strtolower($statusText);
        if ($errorCode !== null || str_starts_with((string) $statusCode, '3') || str_contains($text, 'fail') || str_contains($text, 'reject')) {
            return 'failed';
        }
        if ($statusCode === '201' || str_contains($text, 'handset') || str_contains($text, 'deliver')) {
            return 'delivered';
        }
        if (str_contains($text, 'queue') || str_contains($text, 'sched') || str_contains($text, 'approval')) {
            return 'queued';
        }

        return 'sent';
    }
}

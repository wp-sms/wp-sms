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
 * Firetext — UK-focused SMS provider (https://www.firetext.co.uk/).
 *
 * Auth: API Key generated in the Firetext dashboard (Settings → API).
 * Username/password auth was supported in v7 but is dropped here — Firetext
 * itself recommends API keys. Send and credit endpoints accept form-encoded
 * params and return plain text in the form `<code>:<credits_used> <description>`.
 * Provider message ID is returned in the `X-Message` response header.
 *
 * Webhooks (DLR + MO) are not signed by Firetext, so we use the BulkSms
 * pattern: an optional `webhook_token` shared via the URL query string.
 * When unset, callbacks are rejected by default.
 */
final class FiretextProvider extends AbstractProvider implements SupportsStatusCallback, SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://www.firetext.co.uk/api';

    public function getId(): string
    {
        return 'firetext';
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
                    'description' => __('Firetext API Key from Settings → API in the Firetext dashboard.', 'wp-sms'),
                ],
                'callback_token' => [
                    'type'        => 'secret',
                    'label'       => __('Callback Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Append ?token=… to the DLR/inbound webhook URL in Firetext\'s API Settings; leave empty to disable webhook auth (callbacks will be rejected).', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from_number' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'MyBrand',
                        'description' => __('3–11 alphanumeric characters; no spaces.', 'wp-sms'),
                    ],
                    'unicode' => [
                        'type'        => 'boolean',
                        'label'       => __('Send as Unicode', 'wp-sms'),
                        'required'    => false,
                        'default'     => false,
                        'description' => __('Enable UTF-8 (70-char segments) instead of GSM-7 (160-char). Leave off for English-only traffic.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        // TODO(templates): revisit if Firetext documents variable substitution
        // for the `template` parameter on /sendsms — current docs only describe
        // a template ID, with no clear story for `{code}` placeholders, so we
        // can't safely opt into the SupportsTemplates catalog yet.
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        $from   = (string) $this->getChannelConfig($message->getChannel(), 'from_number', '');

        if ($apiKey === '') {
            return DeliveryResult::failed(__('Firetext API Key is not configured', 'wp-sms'));
        }
        if ($from === '') {
            return DeliveryResult::failed(__('Firetext Sender ID is not configured for this channel', 'wp-sms'));
        }

        $unicode = (bool) $this->getChannelConfig($message->getChannel(), 'unicode', false);

        $payload = [
            'apiKey'  => $apiKey,
            'from'    => $from,
            'to'      => $message->getRecipient(),
            'message' => $message->getBody(),
            'unicode' => $unicode ? '1' : '0',
        ];

        $result = $this->httpPost(self::API_BASE . '/sendsms', [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query($payload),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(
                sprintf(__('Firetext HTTP %d', 'wp-sms'), $result['code']),
                meta: ['firetext_http_code' => $result['code']],
            );
        }

        [$code, $description] = $this->parseStatusLine($result['body']);

        if ($code !== 0) {
            return DeliveryResult::failed($this->errorMessageFor($code, $description));
        }

        $messageId = wp_remote_retrieve_header($result['response'], 'x-message');
        return DeliveryResult::sent($messageId !== '' ? (string) $messageId : null);
    }

    public function getCredit(): ?string
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return null;
        }

        $result = $this->httpPost(self::API_BASE . '/credit', [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query(['apiKey' => $apiKey]),
        ]);

        if ($result instanceof DeliveryResult || $result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        [$code, $description] = $this->parseStatusLine($result['body']);
        if ($code !== 0) {
            return null;
        }

        // On success, $description is the credit balance (the API returns
        // `0:<balance>` rather than `0:<credits_used> <description>`).
        $balance = trim($description);
        return $balance === '' ? null : sprintf(__('%s credits', 'wp-sms'), $balance);
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/credit', [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query(['apiKey' => $apiKey]),
        ]);

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the Firetext API. Check your server\'s internet connection.', 'wp-sms'),
            );
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from Firetext (HTTP %d)', 'wp-sms'), $result['code']),
            );
        }

        [$code, $description] = $this->parseStatusLine($result['body']);

        if ($code === 0) {
            $balance = trim($description);
            return TestConnectionResult::ok(
                $balance !== ''
                    ? sprintf(__('Connected to Firetext — Balance: %s credits', 'wp-sms'), $balance)
                    : __('Connected to Firetext', 'wp-sms'),
                $balance !== '' ? ['balance' => $balance] : [],
            );
        }

        if ($code === 1) {
            return TestConnectionResult::error(__('Invalid Firetext API Key', 'wp-sms'));
        }

        if ($code === 7) {
            // Auth worked, but the account is out of credit. Treat as connectable
            // since the credentials are valid — surface the warning in the message.
            return TestConnectionResult::ok(__('Connected to Firetext — account has insufficient credit', 'wp-sms'));
        }

        return TestConnectionResult::error($this->errorMessageFor($code, $description));
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return $this->callbackUrl('status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->validateCallbackToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $providerId = (string) ($request->get_param('id') ?? '');
        $rawStatus  = strtolower((string) ($request->get_param('status') ?? ''));
        if ($providerId === '' || $rawStatus === '') {
            return [];
        }

        $status = match ($rawStatus) {
            'delivered'                                    => 'delivered',
            'failed', 'rejected', 'expired', 'undelivered' => 'failed',
            'sent'                                         => 'sent',
            'pending', 'queued', 'submitted'               => 'queued',
            default                                        => 'sent',
        };

        $permanent = $status === 'failed' && in_array($rawStatus, ['rejected', 'expired', 'undelivered'], true);
        $reason    = (string) ($request->get_param('reason') ?? '');

        return [new StatusUpdate(
            providerId:   $providerId,
            status:       $status,
            errorCode:    $status === 'failed' ? ($reason !== '' ? $reason : $rawStatus) : null,
            errorMessage: $status === 'failed' ? sprintf(__('Firetext: delivery %s', 'wp-sms'), $rawStatus) : null,
            permanent:    $permanent,
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return $this->callbackUrl('inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->validateCallbackToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $from = (string) ($request->get_param('from') ?? '');
        $body = (string) ($request->get_param('message') ?? $request->get_param('body') ?? '');
        if ($from === '' || $body === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($request->get_param('to') ?? ''),
            body:       $body,
            providerId: (($id = $request->get_param('id')) !== null && $id !== '') ? (string) $id : null,
            meta:       array_filter([
                'received' => $request->get_param('received'),
            ], fn($v) => $v !== null && $v !== ''),
        )];
    }

    // --- Internal ---

    /**
     * Parse a Firetext plain-text response line of the form `<code>:<rest>`.
     *
     * @return array{0:int,1:string} [code, description] — defaults to [-1, ''] on malformed input
     */
    private function parseStatusLine(string $body): array
    {
        $body = trim($body);
        if ($body === '' || !str_contains($body, ':')) {
            return [-1, $body];
        }

        [$codePart, $rest] = explode(':', $body, 2);
        if (!ctype_digit(trim($codePart))) {
            return [-1, $body];
        }

        return [(int) trim($codePart), trim($rest)];
    }

    private function errorMessageFor(int $code, string $description): string
    {
        return match ($code) {
            1 => __('Invalid API key', 'wp-sms'),
            2 => __('Invalid recipient number(s)', 'wp-sms'),
            3 => __('Sender ID rejected', 'wp-sms'),
            5 => __('Message body rejected', 'wp-sms'),
            7 => __('Insufficient credit', 'wp-sms'),
            default => sprintf(__('Firetext error %1$d: %2$s', 'wp-sms'), $code, $description !== '' ? $description : 'unknown'),
        };
    }

    private function callbackUrl(string $type): string
    {
        $token = $this->getSharedConfig('callback_token');
        $args  = $token ? ['token' => $token] : [];
        return RestRoute::url('callbacks/' . $this->getId() . '/' . $type, $args);
    }

    private function validateCallbackToken(\WP_REST_Request $request): bool
    {
        $token = (string) $this->getSharedConfig('callback_token', '');
        if ($token === '') {
            // No signing scheme + no shared token → reject by default.
            return false;
        }

        $received = $request->get_param('token');
        return is_string($received) && hash_equals($token, $received);
    }
}

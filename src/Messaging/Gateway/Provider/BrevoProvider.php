<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsOptOutDetection;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

// TODO(verify): n/a — Brevo does not offer Verify-as-a-Service.
class BrevoProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsOptOutDetection
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.brevo.com/v3';

    public function getId(): string
    {
        return 'brevo';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generate under Settings → SMTP & API → API Keys (v3). Sent as the api-key header.', 'wp-sms'),
                    'placeholder' => 'xkeysib-...',
                ],
                'webhook_token' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Random shared secret appended to the DLR callback URL as ?token=…. Required for DLR validation — Brevo does not sign webhook payloads.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Approved sender ID — up to 11 alphanumeric or 15 numeric characters.', 'wp-sms'),
                        'placeholder' => 'WSMSAlerts',
                    ],
                    'type' => [
                        'type'        => 'select',
                        'label'       => __('SMS Type', 'wp-sms'),
                        'required'    => false,
                        'default'     => 'transactional',
                        'description' => __('Marketing traffic is subject to time-of-day restrictions in some countries; only use Marketing with explicit recipient consent.', 'wp-sms'),
                        'options'     => [
                            'transactional' => __('Transactional', 'wp-sms'),
                            'marketing'     => __('Marketing', 'wp-sms'),
                        ],
                    ],
                ],
                'whatsapp' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('WhatsApp Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('WhatsApp Business sender number in E.164 form without leading + (e.g. 15551234567). Must be Meta-verified in your Brevo WhatsApp account.', 'wp-sms'),
                        'placeholder' => '15551234567',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('Brevo API key not configured', 'wp-sms'));
        }

        return match ($message->getChannel()) {
            'sms'      => $this->sendSms($message, (string) $apiKey),
            'whatsapp' => $this->sendWhatsApp($message, (string) $apiKey),
            default    => DeliveryResult::failed(
                sprintf(__('Brevo does not support channel %s', 'wp-sms'), $message->getChannel())
            ),
        };
    }

    private function sendSms(MessageInterface $message, string $apiKey): DeliveryResult
    {
        $from = (string) $this->getChannelConfig('sms', 'from', '');
        if ($from === '') {
            return DeliveryResult::failed(__('Brevo SMS sender not configured', 'wp-sms'));
        }

        $type = (string) $this->getChannelConfig('sms', 'type', 'transactional');
        $recipient = ltrim($message->getRecipient(), '+');

        $body = [
            'sender'    => $from,
            'recipient' => $recipient,
            'content'   => $message->getBody(),
            'type'      => $type,
        ];

        $meta = $message->getMeta();
        if (!empty($meta['tag'])) {
            $body['tag'] = (string) $meta['tag'];
        }

        $result = $this->httpPost(self::API_BASE . '/transactionalSMS/send', [
            'headers' => $this->jsonHeaders($apiKey),
            'body'    => wp_json_encode($body),
        ]);

        return $this->parseSendResponse($result, 201);
    }

    private function sendWhatsApp(MessageInterface $message, string $apiKey): DeliveryResult
    {
        $from = (string) $this->getChannelConfig('whatsapp', 'from', '');
        if ($from === '') {
            return DeliveryResult::failed(__('Brevo WhatsApp sender not configured', 'wp-sms'));
        }

        $recipient = ltrim($message->getRecipient(), '+');

        $body = [
            'senderNumber'   => $from,
            'contactNumbers' => [$recipient],
            'text'           => $message->getBody(),
        ];

        $result = $this->httpPost(self::API_BASE . '/whatsapp/sendMessage', [
            'headers' => $this->jsonHeaders($apiKey),
            'body'    => wp_json_encode($body),
        ]);

        return $this->parseSendResponse($result, 201);
    }

    private function parseSendResponse(array|DeliveryResult $result, int $expectedCode = 201): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $code = (int) $result['code'];
        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            $data = [];
        }

        if ($code === 401 || $code === 403) {
            return DeliveryResult::failed(__('Invalid Brevo API key', 'wp-sms'));
        }

        if ($code === 429) {
            return DeliveryResult::failed(
                __('Rate limited by Brevo', 'wp-sms'),
                ['retryable' => true],
            );
        }

        if ($code === $expectedCode) {
            $messageId = $data['messageId'] ?? null;
            return DeliveryResult::queued($messageId !== null ? (string) $messageId : null);
        }

        if ($code >= 500) {
            return DeliveryResult::failed(
                __('Brevo server error', 'wp-sms'),
                ['retryable' => true],
            );
        }

        $errorCode = isset($data['code']) ? (string) $data['code'] : null;
        $message = $data['message'] ?? sprintf('HTTP %d', $code);

        return DeliveryResult::failed(
            (string) $message,
            array_filter([
                'brevo_code' => $errorCode,
                'brevo_http' => $code,
            ], fn($v) => $v !== null),
        );
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $account = $this->fetchAccount((string) $apiKey);
        if ($account instanceof DeliveryResult) {
            return null;
        }

        $credits = $this->extractSmsCredits($account);
        if ($credits === null) {
            return null;
        }

        return $credits . ' SMS';
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/account', [
            'headers' => $this->jsonHeaders((string) $apiKey),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401) {
                return TestConnectionResult::error(__('Invalid Brevo API key', 'wp-sms'));
            }
            if ($result['code'] === 403) {
                return TestConnectionResult::error(__('API key lacks required permissions', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Brevo');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $credits = $this->extractSmsCredits($data);
        $companyName = $data['companyName'] ?? '';

        return TestConnectionResult::ok(
            sprintf(__('Connected — SMS credits: %s', 'wp-sms'), $credits ?? 'N/A'),
            [
                'sms_credits' => $credits,
                'account'     => $companyName,
            ],
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        $token = $this->getSharedConfig('webhook_token');
        return RestRoute::url('callbacks/' . $this->getId() . '/status', $token ? ['token' => $token] : []);
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        $expected = (string) $this->getSharedConfig('webhook_token', '');
        if ($expected === '') {
            return false;
        }
        $provided = (string) ($request->get_param('token') ?? '');
        if ($provided === '') {
            return false;
        }
        return hash_equals($expected, $provided);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $payload = $request->get_json_params();
        if (!is_array($payload) || empty($payload)) {
            $payload = $request->get_params();
        }

        $events = (isset($payload[0]) && is_array($payload[0])) ? $payload : [$payload];

        $updates = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $messageId = $event['message_id'] ?? null;
            $msgStatus = $event['msg_status'] ?? null;
            if ($messageId === null || $messageId === '' || !is_string($msgStatus)) {
                continue;
            }

            $bounceType = isset($event['bounce_type']) ? (string) $event['bounce_type'] : null;
            [$status, $permanent, $unsubscribe] = $this->normalizeBrevoStatus($msgStatus, $bounceType);
            if ($status === '') {
                continue;
            }

            $updates[] = new StatusUpdate(
                providerId:   (string) $messageId,
                status:       $status,
                errorCode:    $msgStatus,
                errorMessage: $status === 'failed'
                    ? sprintf(__('Brevo DLR: %s', 'wp-sms'), $msgStatus)
                    : null,
                permanent:    $permanent,
                unsubscribe:  $unsubscribe,
            );
        }

        return $updates;
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        // Brevo doesn't expose an opt-out signal at send time; opt-out comes
        // from DLR webhooks (msg_status = unsubscribed / blacklisted / hard_bounce)
        // which are handled in parseStatusCallback() via the unsubscribe flag.
        return false;
    }

    // --- Internal ---

    /**
     * @return array<string, mixed>|DeliveryResult
     */
    private function fetchAccount(string $apiKey): array|DeliveryResult
    {
        $result = $this->httpGet(self::API_BASE . '/account', [
            'headers' => $this->jsonHeaders($apiKey),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $code = (int) $result['code'];
        if ($code < 200 || $code >= 300) {
            return DeliveryResult::failed(sprintf('HTTP %d', $code));
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed('Invalid response from Brevo');
        }

        return $data;
    }

    private function extractSmsCredits(array $account): mixed
    {
        $plans = $account['plan'] ?? [];
        if (!is_array($plans)) {
            return null;
        }
        foreach ($plans as $plan) {
            if (is_array($plan) && ($plan['type'] ?? '') === 'sms') {
                return $plan['credits'] ?? null;
            }
        }
        return null;
    }

    private function jsonHeaders(string $apiKey): array
    {
        return [
            'api-key'      => $apiKey,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }

    /**
     * Map Brevo's msg_status to WSMS-internal status, plus permanent/unsubscribe flags.
     *
     * @return array{0: string, 1: bool, 2: bool} [status, permanent, unsubscribe]; status='' to skip.
     */
    private function normalizeBrevoStatus(string $msgStatus, ?string $bounceType): array
    {
        $map = match ($msgStatus) {
            'sent'         => ['sent',      false, false],
            'accepted'     => ['queued',    false, false],
            'delivered'    => ['delivered', false, false],
            'soft_bounce'  => ['failed',    false, false],
            'hard_bounce'  => ['failed',    true,  true],
            'unsubscribed' => ['failed',    true,  true],
            'blacklisted'  => ['failed',    true,  true],
            'rejected'     => ['failed',    true,  false],
            'skipped'      => ['failed',    false, false],
            // 'replied' and 'subscribed' are not status changes — skip them.
            default        => ['', false, false],
        };

        if ($bounceType === 'hard') {
            $map[1] = true;
        }

        return $map;
    }
}

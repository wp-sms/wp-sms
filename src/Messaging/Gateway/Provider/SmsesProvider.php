<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

/**
 * SMS.es — Spanish SMS gateway resold on Horisen's SMSC HTTP product.
 *
 * Auth: nested {auth: {username, password}} in JSON body.
 * Send: POST {base}/bulk/sendsms returning HTTP 202 with {msgId, numParts}.
 * DLR: per-send dlrUrl + dlrMask; no signature scheme — token-in-URL pattern.
 *
 * The endpoint host is per-customer (SMS.es delivers it via Excel after
 * onboarding), so api_base_url is a configurable string. The default mirrors
 * the v7 plugin's hardcoded value for migration continuity. TLS verification
 * is disabled because SMS.es serves a real Sectigo cert with CN=*.sms.es but
 * customers are given IP-based URLs, which fail hostname validation.
 */
class SmsesProvider extends AbstractProvider implements SupportsStatusCallback
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const DEFAULT_BASE = 'https://194.0.137.110:42161/';

    // dlrMask bitmask: DELIVERED(1) | UNDELIVERED(2) | REJECTED(16)
    private const DLR_MASK = 19;

    public function getId(): string
    {
        return 'smses';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_base_url' => [
                    'type'        => 'string',
                    'label'       => __('API Base URL', 'wp-sms'),
                    'required'    => true,
                    'default'     => self::DEFAULT_BASE,
                    'placeholder' => self::DEFAULT_BASE,
                    'description' => __('Endpoint URL provided by SMS.es with your account credentials. Trailing slash optional.', 'wp-sms'),
                ],
                'username' => [
                    'type'        => 'string',
                    'label'       => __('Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('SMS.es API username (System ID).', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('SMS.es API password.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Alphanumeric (max 11 chars) or numeric sender. Subject to local regulations.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'delivery_receipt' => true,
            'flash_sms'        => true,
            'incoming'         => false,
            'mms'              => false,
            'unicode'          => true,
            'test_connection'  => false,
        ]);
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $base = rtrim((string) ($this->getSharedConfig('api_base_url') ?: self::DEFAULT_BASE), '/');
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');
        $sender = $this->getChannelConfig('sms', 'sender');

        if (!$base || !$username || !$password) {
            return DeliveryResult::failed(__('SMS.es credentials not configured', 'wp-sms'));
        }
        if (empty($sender)) {
            return DeliveryResult::failed(__('SMS.es Sender ID is not configured', 'wp-sms'));
        }

        $body = [
            'type'     => 'text',
            'auth'     => ['username' => $username, 'password' => $password],
            'sender'   => $sender,
            'receiver' => $message->getRecipient(),
            'text'     => $message->getBody(),
            'dcs'      => preg_match('/[^\x00-\x7F]/', $message->getBody()) ? 'ucs' : 'gsm',
            'dlrUrl'   => $this->getStatusCallbackUrl(),
            'dlrMask'  => self::DLR_MASK,
        ];

        $meta = $message->getMeta();
        if (!empty($meta['flash'])) {
            $body['flash'] = true;
        }

        $result = $this->httpPost($base . '/bulk/sendsms', [
            'headers' => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body'      => wp_json_encode($body),
            'sslverify' => false,
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            $data = [];
        }

        if ($result['code'] === 202 && !empty($data['msgId'])) {
            return DeliveryResult::sent(providerId: (string) $data['msgId']);
        }

        $err = $data['error']['message']
            ?? $data['errorMessage']
            ?? $data['message']
            ?? sprintf(__('SMS.es send failed (HTTP %d)', 'wp-sms'), $result['code']);

        return DeliveryResult::failed($err);
    }

    public function getCredit(): ?string
    {
        return null;
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
        if (!$this->getSharedConfig('password')) {
            return false;
        }
        return hash_equals($this->callbackToken(), (string) ($request->get_param('token') ?? ''));
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $data = $request->get_json_params() ?: [];
        $msgId = $data['msgId'] ?? null;
        $event = $data['event'] ?? null;

        if (empty($msgId) || empty($event)) {
            return [];
        }

        $status = match ($event) {
            'DELIVERED'               => 'delivered',
            'BUFFERED'                => 'queued',
            'SENT_TO_SMSC'            => 'sent',
            'UNDELIVERED', 'REJECTED' => 'failed',
            default                   => 'failed',
        };

        return [new StatusUpdate(
            providerId:   (string) $msgId,
            status:       $status,
            errorCode:    isset($data['errorCode']) ? (string) $data['errorCode'] : null,
            errorMessage: $data['errorMessage'] ?? null,
            permanent:    $event === 'REJECTED',
        )];
    }

    private function callbackToken(): string
    {
        return hash_hmac('sha256', 'smses-callback', (string) $this->getSharedConfig('password'));
    }
}

<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * CloudTalk — cloud call-center / VoIP product with an outbound SMS API.
 *
 * Send-only port: SMS requires Essential tier or higher and must be enabled
 * by CloudTalk support per their help center (no self-serve toggle).
 *
 * Auth:  HTTP Basic — base64(AccessKeyID:AccessKeySecret).
 * Send:  POST https://my.cloudtalk.io/api/sms/send.json — body fields
 *        recipient (E.164), sender (E.164 CloudTalk-purchased SMS-capable
 *        number), message. 60 req/min account-wide rate limit.
 * Probe: GET  https://my.cloudtalk.io/api/agents/index.json — used by
 *        testConnection() to validate credentials cheaply (200 = ok,
 *        401/403 = invalid creds).
 */
final class CloudTalkProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_URL  = 'https://my.cloudtalk.io/api/sms/send.json';
    private const PROBE_URL = 'https://my.cloudtalk.io/api/agents/index.json';

    public function getId(): string
    {
        return 'cloudtalk';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'access_key_id' => [
                    'type'        => 'string',
                    'label'       => __('Access Key ID', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Found in **Account → Settings → API Keys** in the CloudTalk dashboard.', 'wp-sms'),
                    'placeholder' => 'cloudtalk-access-key-id',
                ],
                'access_key_secret' => [
                    'type'        => 'secret',
                    'label'       => __('Access Key Secret', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Found in **Account → Settings → API Keys** in the CloudTalk dashboard.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from_number' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('CloudTalk-purchased SMS-capable number in E.164 format.', 'wp-sms'),
                        'placeholder' => '+14155550100',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $accessKeyId     = (string) $this->getSharedConfig('access_key_id', '');
        $accessKeySecret = (string) $this->getSharedConfig('access_key_secret', '');

        if ($accessKeyId === '' || $accessKeySecret === '') {
            return DeliveryResult::failed(__('CloudTalk credentials not configured', 'wp-sms'));
        }

        $from = (string) $this->getChannelConfig('sms', 'from_number', '');
        if ($from === '') {
            return DeliveryResult::failed(__('CloudTalk Sender Number not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::SEND_URL, [
            'headers' => $this->authHeaders($accessKeyId, $accessKeySecret),
            'body'    => [
                'recipient' => $message->getRecipient(),
                'sender'    => $from,
                'message'   => $message->getBody(),
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return DeliveryResult::failed(
                sprintf(__('CloudTalk network error: %s', 'wp-sms'), $result->error ?? ''),
                ['cloudtalk_error' => 'cloudtalk_network_error'],
            );
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] < 200 || $result['code'] >= 300) {
            $error = is_array($data)
                ? ($data['message'] ?? $data['error'] ?? $result['body'])
                : ($result['body'] !== '' ? $result['body'] : sprintf('HTTP %d', $result['code']));

            return DeliveryResult::failed(
                (string) $error,
                [
                    'cloudtalk_error' => 'cloudtalk_api_error',
                    'status'          => $result['code'],
                ],
            );
        }

        // Response shape is not publicly documented — pull message id from
        // the most likely keys, fall back to a generated UUID so logs/DLR
        // joins still work.
        $providerId = null;
        if (is_array($data)) {
            $providerId = $data['responseData']['id']
                ?? $data['data']['id']
                ?? $data['id']
                ?? $data['message_id']
                ?? null;
        }
        if ($providerId !== null) {
            $providerId = (string) $providerId;
        } else {
            $providerId = $this->fallbackProviderId();
        }

        return DeliveryResult::queued($providerId);
    }

    private function fallbackProviderId(): string
    {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }

        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff),
        );
    }

    /**
     * CloudTalk does not expose an account-balance endpoint as of 2026-05-09.
     */
    public function getCredit(): ?string
    {
        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        $accessKeyId     = $this->getSharedConfig('access_key_id');
        $accessKeySecret = $this->getSharedConfig('access_key_secret');

        if (!$accessKeyId || !$accessKeySecret) {
            return TestConnectionResult::error(__('Access Key ID and Access Key Secret are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::PROBE_URL, [
            'headers' => $this->authHeaders($accessKeyId, $accessKeySecret),
        ]);

        if (!$result instanceof DeliveryResult
            && ($result['code'] === 401 || $result['code'] === 403)) {
            return TestConnectionResult::error(__('Invalid Access Key ID or Access Key Secret', 'wp-sms'));
        }

        $data = $this->validateTestResponse($result, 'CloudTalk');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        return TestConnectionResult::ok(__('CloudTalk credentials are valid.', 'wp-sms'));
    }

    private function authHeaders(string $accessKeyId, string $accessKeySecret): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode($accessKeyId . ':' . $accessKeySecret),
            'Accept'        => 'application/json',
        ];
    }

    // TODO(status-callback): CloudTalk webhook DLR not implemented — signature
    // scheme not published as of 2026-05-09. Add SupportsStatusCallback once
    // verified.
    //
    // TODO(inbound): inbound SMS webhook reportedly exists at Integrations →
    // Webhooks (sms.received) but the signature scheme is not published as of
    // 2026-05-09. Defer SupportsInboundMessage until verified — shipping an
    // unsigned webhook handler would be a security regression.
    //
    // TODO(verify): CloudTalk has no Verify-as-a-Service endpoint — N/A even
    // when SupportsVerify lands.
}

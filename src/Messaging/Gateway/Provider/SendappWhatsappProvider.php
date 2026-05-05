<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * SendApp WhatsApp Connect (api.sendapp.live) — sends WhatsApp messages from
 * the user's own WhatsApp account paired via the SendApp Connect desktop app.
 *
 * This rides on the unofficial WhatsApp Web protocol (QR-pair your account),
 * not Meta's WABA — the linked phone must stay online for messages to send.
 * The public docs do not specify a webhook payload format, so this provider
 * implements send + testConnection only.
 */
class SendappWhatsappProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_SEND = 'https://api.sendapp.live/send.php';

    public function getId(): string
    {
        return 'sendappwhatsapp';
    }

    public function getSupportedChannels(): array
    {
        return ['whatsapp'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'instance_id' => [
                    'type'        => 'string',
                    'label'       => __('Instance ID', 'wp-sms'),
                    'required'    => true,
                    'description' => __('From the SendApp Connect desktop app after pairing your WhatsApp account.', 'wp-sms'),
                ],
                'access_token' => [
                    'type'        => 'secret',
                    'label'       => __('Access Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('From the SendApp Connect desktop app, alongside the Instance ID.', 'wp-sms'),
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $instanceId  = $this->getSharedConfig('instance_id');
        $accessToken = $this->getSharedConfig('access_token');

        if (!$instanceId || !$accessToken) {
            return DeliveryResult::failed(__('SendApp WhatsApp credentials not configured', 'wp-sms'));
        }

        $mediaUrls = $message->getMeta()['media_urls'] ?? [];
        $mediaUrl  = !empty($mediaUrls) ? (string) $mediaUrls[0] : '';
        $hasMedia  = $mediaUrl !== '';

        $payload = [
            'number'       => $message->getRecipient(),
            'type'         => $hasMedia ? 'media' : 'text',
            'message'      => $message->getBody(),
            'instance_id'  => $instanceId,
            'access_token' => $accessToken,
        ];

        if ($hasMedia) {
            $payload['media_url'] = $mediaUrl;
            $payload['filename']  = basename(parse_url($mediaUrl, PHP_URL_PATH) ?: $mediaUrl);
        }

        $result = $this->httpPost(self::API_SEND, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query($payload),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid SendApp WhatsApp credentials', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] < 200 || $result['code'] >= 300) {
            $error = is_array($data) ? ($data['message'] ?? $data['error'] ?? null) : null;
            return DeliveryResult::failed($error ?: sprintf('HTTP %d', $result['code']));
        }

        if (!is_array($data)) {
            return DeliveryResult::failed(__('Invalid response from SendApp WhatsApp', 'wp-sms'));
        }

        $isSuccess = ($data['success'] ?? null) === true
            || ($data['status'] ?? null) === 'success';

        if (!$isSuccess) {
            $error = $data['message'] ?? $data['error'] ?? __('SendApp WhatsApp did not accept the message', 'wp-sms');
            return DeliveryResult::failed(is_string($error) ? $error : __('SendApp WhatsApp send failed', 'wp-sms'));
        }

        $providerId = $data['data']['id']
            ?? $data['data']['messageID']
            ?? $data['id']
            ?? null;

        return DeliveryResult::sent(providerId: $providerId !== null ? (string) $providerId : null);
    }

    /**
     * SendApp WhatsApp Connect has no balance/account endpoint, so verify by
     * sending a deliberately invalid request: an empty `number` with valid
     * credentials returns a "missing number" error (credentials accepted),
     * while bad credentials surface as an instance/auth error.
     */
    public function testConnection(): TestConnectionResult
    {
        $instanceId  = $this->getSharedConfig('instance_id');
        $accessToken = $this->getSharedConfig('access_token');

        if (!$instanceId || !$accessToken) {
            return TestConnectionResult::error(__('Instance ID and Access Token are required', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_SEND, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query([
                'number'       => '',
                'type'         => 'text',
                'message'      => '',
                'instance_id'  => $instanceId,
                'access_token' => $accessToken,
            ]),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Instance ID or Access Token', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'SendApp WhatsApp');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $message = strtolower((string) ($data['message'] ?? $data['error'] ?? ''));
        if ($message !== '' && $this->looksLikeAuthError($message)) {
            return TestConnectionResult::error(__('Invalid Instance ID or Access Token', 'wp-sms'));
        }

        return TestConnectionResult::ok(__('Connection successful', 'wp-sms'));
    }

    private function looksLikeAuthError(string $lowercaseMessage): bool
    {
        foreach (['invalid token', 'invalid access', 'invalid instance', 'unauthorized', 'access_token', 'instance_id'] as $needle) {
            if (str_contains($lowercaseMessage, $needle)) {
                return true;
            }
        }
        return false;
    }
}

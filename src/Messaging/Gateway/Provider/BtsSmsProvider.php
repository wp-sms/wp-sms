<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * BtsSMS — Bangladesh-only SMS reseller operated by BTS Communications (BD)
 * Ltd. Brand site btssms.com, customer dashboard at btssms.com/login,
 * send API on btssms.com/smsapi.
 *
 * Send: GET https://btssms.com/smsapi
 *   ?api_key=…&type=text|unicode&senderid=…&number=…&message=…
 *   Response: bare numeric code. 202 / 1001 = success; 1002+ = error codes.
 *
 * API shape inference: btssms.com publishes no public API doc page. The
 * shape is anchored on (1) the live /smsapi endpoint returning the literal
 * "1003" ("required fields missing") on bare hits — the canonical code
 * from the BulkSMSBD reseller platform — and (2) btssms.com running the
 * same Laravel-based reseller stack as BulkSMSBD and sister BD providers
 * (sms.net.bd, sms.bd, alpha.net.bd, bulksmsbd.net). The endpoint,
 * parameter names, and response-code table are mirrored from the
 * BulkSMSBD public reference: https://bulksmsbd.com/bulksms-api-bangladesh.php
 *
 * Auto-unicode: bodies containing any non-printable-ASCII character are
 * sent with type=unicode (Bengali / non-Latin); otherwise type=text.
 *
 * Deferred capabilities:
 *
 * @todo dlr — DLR/webhook isn't documented on the sister-platform public
 *   docs and probing returned nothing; verify once an account is available.
 * @todo inbound — same reason as DLR; no documented MO webhook.
 * @todo balance — provider exposes no public balance endpoint (probed
 *   /getBalanceApi, /api/getBalanceApi, /balance, /smsapi/balance,
 *   /getBalance, /check_balance, /api/balance, /balanceapi,
 *   /smsapi/getBalance — all 404).
 */
final class BtsSmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_URL = 'https://btssms.com/smsapi';

    public function getId(): string
    {
        return 'btssms';
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
                    'description' => __('Generated in your btssms.com customer dashboard.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'WPSMS',
                        'description' => __('Pre-approved Sender ID. Register and activate it with BtsSMS support before sending.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        $sender = (string) $this->getChannelConfig('sms', 'sender_id', '');

        if ($apiKey === '') {
            return DeliveryResult::failed(__('BtsSMS API Key not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('BtsSMS Sender ID not configured', 'wp-sms'));
        }

        $type = preg_match('/[^\x20-\x7e]/', $message->getBody()) ? 'unicode' : 'text';

        $url = self::API_URL . '?' . http_build_query([
            'api_key'  => $apiKey,
            'type'     => $type,
            'senderid' => $sender,
            'number'   => $message->getRecipient(),
            'message'  => $message->getBody(),
        ]);

        $result = $this->httpGet($url);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            $body = trim((string) $result['body']);
            $error = $body !== ''
                ? $body
                : sprintf(__('BtsSMS send failed (HTTP %d)', 'wp-sms'), $result['code']);
            return DeliveryResult::failed($error);
        }

        $body = trim((string) $result['body']);

        if ($body === '' || !ctype_digit($body)) {
            return DeliveryResult::failed(
                $body !== '' ? $body : __('BtsSMS returned an unexpected response', 'wp-sms')
            );
        }

        if ($body === '202' || $body === '1001') {
            return DeliveryResult::sent();
        }

        return DeliveryResult::failed(
            $this->describeSendError($body),
            meta: ['btssms_code' => $body],
        );
    }

    public function getCredit(): ?string
    {
        // No public balance endpoint exposed by btssms.com or the shared
        // BulkSMSBD-family reseller platform. See class-level @todo.
        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        // Sentinel call: fire a deliberately-malformed send. If the api_key
        // is wrong the server returns 1010; if it's right we get a
        // params-related code (1003 / 1011 / 1012 / etc) — i.e. the server
        // validated the key, then complained about the rest.
        $url = self::API_URL . '?' . http_build_query([
            'api_key'  => $apiKey,
            'type'     => 'text',
            'senderid' => '',
            'number'   => '',
            'message'  => '',
        ]);

        $result = $this->httpGet($url);

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the BtsSMS API. Check your server\'s internet connection.', 'wp-sms')
            );
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from BtsSMS (HTTP %d)', 'wp-sms'), $result['code'])
            );
        }

        $body = trim((string) $result['body']);

        if ($body === '1010') {
            return TestConnectionResult::error($this->describeSendError('1010'));
        }

        if ($body === '' || !ctype_digit($body)) {
            return TestConnectionResult::error(__('Invalid response from BtsSMS', 'wp-sms'));
        }

        // Any other numeric code (1003, 1011, 1012, 202, 1001, ...) means
        // the server accepted the key and reached parameter validation.
        return TestConnectionResult::ok(__('Connected to BtsSMS', 'wp-sms'));
    }

    private function describeSendError(string $code): string
    {
        switch ($code) {
            case '1002':
                return __('Sender ID not correct or disabled', 'wp-sms');
            case '1003':
                return __('Required fields missing', 'wp-sms');
            case '1005':
                return __('Internal error', 'wp-sms');
            case '1006':
                return __('Balance validity not available', 'wp-sms');
            case '1007':
                return __('Insufficient balance', 'wp-sms');
            case '1010':
                return __('Invalid API Key', 'wp-sms');
            case '1011':
                return __('User ID not found', 'wp-sms');
            case '1012':
                return __('Masking SMS must be in Bengali', 'wp-sms');
            case '1013':
                return __('Sender ID not found', 'wp-sms');
            case '1014':
                return __('Sender Type not found', 'wp-sms');
            case '1015':
                return __('SMS Gateway not found', 'wp-sms');
            case '1016':
                return __('Price info not found for this sender', 'wp-sms');
            case '1017':
                return __('Price info not found for this sender type', 'wp-sms');
            case '1018':
                return __('Account disabled', 'wp-sms');
            case '1019':
                return __('Price info not found for this sender ID', 'wp-sms');
            case '1020':
                return __('Parent account not found', 'wp-sms');
            case '1021':
                return __('Parent active price info not found', 'wp-sms');
            default:
                return $code;
        }
    }
}

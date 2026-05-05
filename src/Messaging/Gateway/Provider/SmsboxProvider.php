<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * SMSBox.be — Belgian SMS gateway operated by Jaan Team.
 *
 * Modeled on the official SDK at github.com/JaanTeam/smsbox-php-api
 * (src/SmsBox.php). Marketing copy on jaan.be lists more channels
 * (WhatsApp etc.) but the public SDK only exposes SMS / voice TTS / OTP /
 * HLR endpoints, so we follow the SDK.
 *
 * Auth: header `X-Api-Key: <key>` on every request.
 * Send: POST core.smsbox.be/api/v1/sendsms — application/x-www-form-urlencoded.
 * Balance: GET core.smsbox.be/api/v1/balance — JSON `{"code":100,"message":"<float>"}`.
 * Auth check: GET core.smsbox.be/api/v1/auth — success code is 10 (NOT 100).
 *
 * Channels: SMS only. Voice TTS, HLR and provider-managed OTP are not
 * implemented because WSMS has no first-class voice/validation/OTP-relay
 * primitives yet.
 *
 * Delivery receipts: SMSBox accepts a per-send `noti` URL but does not
 * publicly document the webhook payload, so SupportsStatusCallback is NOT
 * implemented — turning it on would be speculation.
 */
final class SmsboxProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE    = 'https://core.smsbox.be/api/v1';
    private const SEND_URL    = self::API_BASE . '/sendsms';
    private const BALANCE_URL = self::API_BASE . '/balance';
    private const AUTH_URL    = self::API_BASE . '/auth';

    /** @var array<int, string> Verbatim from the SDK's exception classes. */
    private const ERROR_MESSAGES = [
        1  => 'Sender not OK',
        4  => 'Invalid API key',
        5  => 'Phone number has no prefix',
        6  => 'SMS has no message',
        7  => 'SMS max characters reached',
        8  => 'Number not provided',
        9  => 'No valid phone number',
        10 => 'Not enough credits',
        11 => 'Maximum number of phone numbers reached',
        13 => 'Sender phone number is too long',
    ];

    public function getId(): string
    {
        return 'smsbox';
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
                    'description' => __('Your SMSBox API key from the SMSBox dashboard at core.smsbox.be.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Optional. Custom alphanumeric sender IDs are accepted only for non-Belgian recipients (per the SMSBox SDK).', 'wp-sms'),
                    ],
                    'longsms' => [
                        'type'        => 'boolean',
                        'label'       => __('Allow Long SMS', 'wp-sms'),
                        'default'     => true,
                        'description' => __('Concatenate messages longer than 160 characters into multiple segments. Each segment is billed separately.', 'wp-sms'),
                    ],
                    'tts_language' => [
                        'type'        => 'select',
                        'label'       => __('TTS Language', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Language used when a message is sent as text-to-speech (only when the dispatch explicitly requests TTS via message metadata).', 'wp-sms'),
                        'options'     => [
                            ['value' => '',   'label' => __('— Not set —', 'wp-sms')],
                            ['value' => 'NL', 'label' => __('Dutch (NL)', 'wp-sms')],
                            ['value' => 'EN', 'label' => __('English (EN)', 'wp-sms')],
                            ['value' => 'FR', 'label' => __('French (FR)', 'wp-sms')],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');

        if ($apiKey === '') {
            return DeliveryResult::failed(__('SMSBox API key not configured', 'wp-sms'));
        }

        $payload = [
            'numbers' => ltrim($message->getRecipient(), '+'),
            'message' => $message->getBody(),
        ];

        if ($this->getChannelConfig('sms', 'longsms', true)) {
            $payload['longsms'] = 1;
        }

        $from = (string) $this->getChannelConfig('sms', 'from', '');
        if ($from !== '') {
            $payload['from'] = $from;
        }

        $meta = $message->getMeta();
        if (!empty($meta['tts'])) {
            $payload['tts']    = 1;
            $payload['ttslng'] = (string) ($meta['tts_language'] ?? $this->getChannelConfig('sms', 'tts_language', ''));
        }

        $result = $this->httpPost(self::SEND_URL, [
            'headers' => [
                'X-Api-Key'    => $apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body'    => http_build_query($payload),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(__('Invalid call to SmsBox', 'wp-sms'));
        }

        $response = json_decode($result['body'], true);
        $code     = isset($response['code']) ? (int) $response['code'] : null;

        if ($code === 100) {
            $providerId = null;
            if (isset($response['message']) && is_array($response['message'])) {
                $first = $response['message'][0] ?? null;
                if (is_array($first) && isset($first['id'])) {
                    $providerId = (string) $first['id'];
                }
            }
            return DeliveryResult::sent(providerId: $providerId);
        }

        $error = $code !== null
            ? (self::ERROR_MESSAGES[$code] ?? sprintf(__('Unknown error, code %d', 'wp-sms'), $code))
            : __('Invalid response from SmsBox', 'wp-sms');

        return DeliveryResult::failed($error, meta: $code !== null ? ['smsbox_code' => $code] : []);
    }

    public function getCredit(): ?string
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return null;
        }

        $result = $this->httpGet(self::BALANCE_URL, [
            'headers' => ['X-Api-Key' => $apiKey],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $response = json_decode($result['body'], true);
        if (!is_array($response) || ($response['code'] ?? null) !== 100 || !isset($response['message'])) {
            return null;
        }

        return (string) (float) $response['message'];
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return TestConnectionResult::error(__('API key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::AUTH_URL, [
            'headers' => ['X-Api-Key' => $apiKey],
        ]);

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the SmsBox API. Check your server\'s internet connection.', 'wp-sms')
            );
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from SmsBox (HTTP %d)', 'wp-sms'), $result['code'])
            );
        }

        $response = json_decode($result['body'], true);
        $code     = is_array($response) && isset($response['code']) ? (int) $response['code'] : null;

        if ($code === 21) {
            return TestConnectionResult::error(__('Invalid SmsBox API key', 'wp-sms'));
        }

        if ($code !== 10) {
            return TestConnectionResult::error(
                $code !== null
                    ? sprintf(__('SmsBox rejected the request (code %d)', 'wp-sms'), $code)
                    : __('Invalid response from SmsBox', 'wp-sms')
            );
        }

        // Auth OK — fetch balance for a richer success message. Failure here is non-fatal.
        $balance = $this->getCredit();
        if ($balance !== null) {
            return TestConnectionResult::ok(
                sprintf(__('Connected — Balance: %s', 'wp-sms'), $balance),
                ['balance' => $balance],
            );
        }

        return TestConnectionResult::ok(__('Connected to SmsBox', 'wp-sms'));
    }
}

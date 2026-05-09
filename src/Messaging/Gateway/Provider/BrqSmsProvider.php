<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * BrqSMS — Sudan-based premium SMS aggregator (Khartoum HQ) operated by
 * Mazin Host. Brand site brqsms.com, customer dashboard client.mazinhost.com,
 * send/credit API on mazinhost.com.
 *
 * Send: GET https://mazinhost.com/smsv1/sms/api
 *   ?action=send-sms&api_key=…&to=…&sms=…&from=…&unicode=0|1
 *   Response: bare integer — positive int = message id, negative int = error code.
 *
 * Credit / test connection: GET …?action=check-balance&api_key=…
 *   Response (JSON): {"balance": "..."} on success, {"message": "-101|-102"} on auth failure.
 *
 * Auto-unicode: bodies containing any non-printable-ASCII character are sent
 * with unicode=1; otherwise unicode=0.
 *
 * Deferred capabilities:
 *
 * @todo media/MMS — error code -110 ("Media url required") hints at a media
 *   path but the provider does not document the request shape; defer until a
 *   live request can be captured.
 * @todo callback — no DLR or inbound MO webhook is documented.
 * @todo verify — no /verify/start endpoint; OTP traffic flows through
 *   standard /sendsms.
 */
final class BrqSmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://mazinhost.com/smsv1/sms/api';

    public function getId(): string
    {
        return 'brqsms';
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
                    'description' => __('Generated in your client.mazinhost.com client area under API.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'WPSMS',
                        'description' => __('Pre-approved Sender ID. Register and activate it with BrqSMS support before sending.', 'wp-sms'),
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
            return DeliveryResult::failed(__('BrqSMS API Key not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('BrqSMS Sender ID not configured', 'wp-sms'));
        }

        $unicode = preg_match('/[^\x20-\x7e]/', $message->getBody()) ? 1 : 0;

        $url = self::API_BASE . '?' . http_build_query([
            'action'  => 'send-sms',
            'api_key' => $apiKey,
            'to'      => $message->getRecipient(),
            'sms'     => $message->getBody(),
            'from'    => $sender,
            'unicode' => $unicode,
        ]);

        $result = $this->httpGet($url);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            $body = trim((string) $result['body']);
            $error = $body !== ''
                ? $body
                : sprintf(__('BrqSMS send failed (HTTP %d)', 'wp-sms'), $result['code']);
            return DeliveryResult::failed($error);
        }

        $decoded = json_decode($result['body'], true);

        if (!is_int($decoded)) {
            $body = trim((string) $result['body']);
            return DeliveryResult::failed(
                $body !== '' ? $body : __('BrqSMS returned an unexpected response', 'wp-sms')
            );
        }

        if ($decoded >= 0) {
            return DeliveryResult::sent(providerId: (string) $decoded);
        }

        return DeliveryResult::failed(
            $this->describeSendError((string) $decoded),
            meta: ['brqsms_code' => (string) $decoded],
        );
    }

    public function getCredit(): ?string
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '?' . http_build_query([
            'action'  => 'check-balance',
            'api_key' => $apiKey,
        ]));

        if ($result instanceof DeliveryResult) {
            return null;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $decoded = json_decode($result['body'], true);

        if (!is_array($decoded) || !isset($decoded['balance'])) {
            return null;
        }

        return (string) $decoded['balance'];
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '?' . http_build_query([
            'action'  => 'check-balance',
            'api_key' => $apiKey,
        ]));

        $data = $this->validateTestResponse($result, 'BrqSMS');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (isset($data['balance'])) {
            $balance = (string) $data['balance'];
            return TestConnectionResult::ok(
                sprintf(__('Connected — Credit: %s', 'wp-sms'), $balance),
                ['credit' => $balance],
            );
        }

        if (isset($data['message'])) {
            return TestConnectionResult::error($this->describeBalanceError((string) $data['message']));
        }

        return TestConnectionResult::error(__('Invalid response from BrqSMS', 'wp-sms'));
    }

    private function describeSendError(string $code): string
    {
        switch ($code) {
            case '-100':
                return __('Bad gateway requested', 'wp-sms');
            case '-101':
                return __('Wrong action', 'wp-sms');
            case '-102':
                return __('Authentication failed', 'wp-sms');
            case '-103':
                return __('Invalid phone number', 'wp-sms');
            case '-104':
                return __('Phone coverage not active', 'wp-sms');
            case '-105':
                return __('Insufficient balance', 'wp-sms');
            case '-106':
                return __('Invalid Sender ID', 'wp-sms');
            case '-107':
                return __('Invalid SMS Type', 'wp-sms');
            case '-108':
                return __('SMS Gateway not active', 'wp-sms');
            case '-109':
                return __('Invalid Schedule Time', 'wp-sms');
            case '-110':
                return __('Media url required', 'wp-sms');
            case '-111':
                return __('SMS contains a spam word and is awaiting approval', 'wp-sms');
            default:
                return $code;
        }
    }

    private function describeBalanceError(string $code): string
    {
        switch ($code) {
            case '-101':
                return __('Missing parameters (API Key not provided)', 'wp-sms');
            case '-102':
                return __('Account not exist (wrong API Key)', 'wp-sms');
            default:
                return $code;
        }
    }
}

<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * eBulkSMS — Nigerian aggregator (SMS + WhatsApp).
 *
 * Auth: username + apikey embedded in the JSON body. No DLR webhook, no
 * inbound MO, no template catalog documented.
 */
class EbulkSmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = true;

    private const SMS_ENDPOINT      = 'https://api.ebulksms.com/sendsms.json';
    private const WHATSAPP_ENDPOINT = 'https://api.ebulksms.com/sendwhatsapp.json';
    private const BALANCE_ENDPOINT  = 'https://api.ebulksms.com/balance';

    public function getId(): string
    {
        return 'ebulksms';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'username' => [
                    'type'        => 'string',
                    'label'       => __('Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Login email for your eBulkSMS account.', 'wp-sms'),
                ],
                'apikey' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generated API key from your eBulkSMS dashboard.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => false,
                        'placeholder' => 'WSMS',
                        'description' => __('Alphanumeric sender ID (up to 11 characters) or numeric (up to 14). Leave blank for the account default.', 'wp-sms'),
                    ],
                ],
                'whatsapp' => [
                    'subject' => [
                        'type'        => 'string',
                        'label'       => __('Default Subject', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Default title for WhatsApp messages; per-message subject can override via meta[\'subject\'].', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $apikey   = (string) $this->getSharedConfig('apikey', '');

        if ($username === '' || $apikey === '') {
            return DeliveryResult::failed(__('eBulkSMS credentials not configured', 'wp-sms'));
        }

        return match ($message->getChannel()) {
            'sms'      => $this->sendSms($message, $username, $apikey),
            'whatsapp' => $this->sendWhatsapp($message, $username, $apikey),
            default    => DeliveryResult::failed(sprintf(__('eBulkSMS does not support channel %s', 'wp-sms'), $message->getChannel())),
        };
    }

    private function sendSms(MessageInterface $message, string $username, string $apikey): DeliveryResult
    {
        $meta = $message->getMeta();
        $msgid = uniqid('wsms_', true);

        $body = [
            'SMS' => [
                'auth' => [
                    'username' => $username,
                    'apikey'   => $apikey,
                ],
                'message' => [
                    'sender'      => (string) $this->getChannelConfig('sms', 'from', ''),
                    'messagetext' => $message->getBody(),
                    'flash'       => !empty($meta['flash']) ? '1' : '0',
                ],
                'recipients' => [
                    'gsm' => [[
                        'msidn' => $this->stripPlus($message->getRecipient()),
                        'msgid' => $msgid,
                    ]],
                ],
            ],
            'dndsender' => '0',
        ];

        return $this->dispatch(self::SMS_ENDPOINT, $body, $msgid);
    }

    private function sendWhatsapp(MessageInterface $message, string $username, string $apikey): DeliveryResult
    {
        $meta = $message->getMeta();
        $subject = (string) (
            $meta['subject']
            ?? $this->getChannelConfig('whatsapp', 'subject', '')
        );
        if ($subject === '') {
            $subject = __('Notification', 'wp-sms');
        }

        $msgid = uniqid('wsms_', true);

        $body = [
            'WA' => [
                'auth' => [
                    'username' => $username,
                    'apikey'   => $apikey,
                ],
                'message' => [
                    'subject'     => $subject,
                    'messagetext' => $message->getBody(),
                ],
                'recipients' => [$this->stripPlus($message->getRecipient())],
            ],
        ];

        return $this->dispatch(self::WHATSAPP_ENDPOINT, $body, $msgid);
    }

    private function dispatch(string $url, array $body, string $msgid): DeliveryResult
    {
        $result = $this->httpPost($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid eBulkSMS credentials', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(sprintf(__('Invalid response from eBulkSMS (HTTP %d)', 'wp-sms'), $result['code']));
        }

        $response = $data['response'] ?? [];
        $status = (string) ($response['status'] ?? '');

        if ($status === 'SUCCESS') {
            return DeliveryResult::sent(
                providerId: $msgid,
                cost: isset($response['cost']) ? (float) $response['cost'] : null,
                meta: array_filter([
                    'ebulksms_status' => $status,
                    'totalsent'       => isset($response['totalsent']) ? (int) $response['totalsent'] : null,
                ], fn($v) => $v !== null),
            );
        }

        return DeliveryResult::failed(
            $this->mapStatusError($status),
            meta: array_filter(['ebulksms_status' => $status !== '' ? $status : null]),
        );
    }

    private function mapStatusError(string $status): string
    {
        return match ($status) {
            'AUTH_FAILURE'        => __('Invalid eBulkSMS credentials', 'wp-sms'),
            'INSUFFICIENT_CREDIT' => __('Insufficient eBulkSMS credit', 'wp-sms'),
            'INVALID_RECIPIENT'   => __('eBulkSMS rejected the recipient number', 'wp-sms'),
            'INVALID_MESSAGE'     => __('eBulkSMS rejected the message body', 'wp-sms'),
            'MISSING_USERNAME'    => __('eBulkSMS username is missing', 'wp-sms'),
            'MISSING_APIKEY'      => __('eBulkSMS API key is missing', 'wp-sms'),
            'INVALID_JSON'        => __('eBulkSMS rejected the request payload', 'wp-sms'),
            ''                    => __('eBulkSMS did not accept the message', 'wp-sms'),
            default               => sprintf(__('eBulkSMS error: %s', 'wp-sms'), $status),
        };
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $apikey   = (string) $this->getSharedConfig('apikey', '');

        if ($username === '' || $apikey === '') {
            return null;
        }

        $result = $this->httpGet($this->balanceUrl($username, $apikey));

        if ($result instanceof DeliveryResult) {
            return null;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $balance = trim((string) $result['body']);
        if ($balance === '' || !is_numeric($balance)) {
            return null;
        }

        return sprintf('%s units', $balance);
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $apikey   = (string) $this->getSharedConfig('apikey', '');

        if ($username === '' || $apikey === '') {
            return TestConnectionResult::error(__('Username and API Key are required', 'wp-sms'));
        }

        $result = $this->httpGet($this->balanceUrl($username, $apikey));

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the eBulkSMS API. Check your server\'s internet connection.', 'wp-sms')
            );
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return TestConnectionResult::error(__('Invalid eBulkSMS credentials', 'wp-sms'));
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from eBulkSMS (HTTP %d)', 'wp-sms'), $result['code'])
            );
        }

        $balance = trim((string) $result['body']);
        if ($balance === '' || !is_numeric($balance)) {
            return TestConnectionResult::error(__('Invalid response from eBulkSMS', 'wp-sms'));
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s units', 'wp-sms'), $balance),
            ['balance' => $balance],
        );
    }

    private function balanceUrl(string $username, string $apikey): string
    {
        return self::BALANCE_ENDPOINT . '/' . rawurlencode($username) . '/' . rawurlencode($apikey);
    }

    private function stripPlus(string $recipient): string
    {
        return ltrim($recipient, '+');
    }
}

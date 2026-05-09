<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

class BulkSmsMaProvider extends AbstractProvider
{
    public const TESTED = false;

    private const SEND_URL    = 'https://bulksms.ma/developer/sms/send';
    private const BALANCE_URL = 'https://bulksms.ma/developer/account/solde';

    public function getId(): string
    {
        return 'bulksmsma';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'token' => [
                    'type'        => 'secret',
                    'label'       => __('API Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your API token from the BulkSMS.ma manager dashboard.', 'wp-sms'),
                    'placeholder' => __('Paste your token', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'shortcode' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Optional 3–11 alphanumeric sender ID approved in your BulkSMS.ma account.', 'wp-sms'),
                        'placeholder' => 'MYBRAND',
                    ],
                ],
            ],
        ];
    }

    // TODO(dlr): provider docs say DLR webhooks are supported but the spec page is
    // "under construction" — defer SupportsStatusCallback until support@bulksms.ma
    // confirms field shape + signature scheme.
    // TODO(inbound): same — defer SupportsInboundMessage.

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $token = $this->getSharedConfig('token');
        if (!$token) {
            return DeliveryResult::failed(__('BulkSMS.ma API token is not configured.', 'wp-sms'));
        }

        $body = [
            'token'   => $token,
            'tel'     => $message->getRecipient(),
            'message' => $message->getBody(),
        ];

        $shortcode = $this->getChannelConfig('sms', 'shortcode');
        if ($shortcode) {
            $body['shortcode'] = $shortcode;
        }

        $result = $this->httpPost(self::SEND_URL, ['body' => $body]);
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $decoded = json_decode($result['body'] ?? '', true);
        if (!is_array($decoded)) {
            return DeliveryResult::failed(__('BulkSMS.ma returned an unparseable response.', 'wp-sms'));
        }

        if (isset($decoded['error'])) {
            return DeliveryResult::failed(sprintf(__('BulkSMS.ma error: %s', 'wp-sms'), (string) $decoded['error']));
        }

        if (($decoded['success'] ?? null) == 1) {
            return DeliveryResult::sent(providerId: null);
        }

        return DeliveryResult::failed(__('BulkSMS.ma did not confirm send success.', 'wp-sms'));
    }

    public function getCredit(): ?string
    {
        $token = $this->getSharedConfig('token');
        if (!$token) {
            return null;
        }

        $result = $this->httpPost(self::BALANCE_URL, ['body' => ['token' => $token]]);
        if ($result instanceof DeliveryResult) {
            return null;
        }

        $decoded = json_decode($result['body'] ?? '', true);
        if (!is_array($decoded) || !isset($decoded['solde'])) {
            return null;
        }
        return (string) $decoded['solde'];
    }

    public function testConnection(): TestConnectionResult
    {
        $token = $this->getSharedConfig('token');
        if (!$token) {
            return TestConnectionResult::error(__('API Token is required.', 'wp-sms'));
        }

        $result = $this->httpPost(self::BALANCE_URL, ['body' => ['token' => $token]]);
        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(__('Could not reach the BulkSMS.ma API. Check your server\'s internet connection.', 'wp-sms'));
        }

        if (($result['code'] ?? 0) < 200 || ($result['code'] ?? 0) >= 300) {
            return TestConnectionResult::error(sprintf(__('Unexpected response from BulkSMS.ma (HTTP %d)', 'wp-sms'), (int) $result['code']));
        }

        $decoded = json_decode($result['body'] ?? '', true);
        if (!is_array($decoded)) {
            return TestConnectionResult::error(__('BulkSMS.ma returned an unparseable response.', 'wp-sms'));
        }
        if (isset($decoded['error'])) {
            return TestConnectionResult::error(sprintf(__('BulkSMS.ma rejected the token: %s', 'wp-sms'), (string) $decoded['error']));
        }

        $credit = $decoded['solde'] ?? null;
        return TestConnectionResult::ok(
            $credit !== null
                ? sprintf(__('Connected — Credit: %s', 'wp-sms'), (string) $credit)
                : __('Connected', 'wp-sms'),
            $credit !== null ? ['credit' => (string) $credit] : []
        );
    }
}

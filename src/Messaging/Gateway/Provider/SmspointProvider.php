<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * SMSpoint — German EU-focused SMS gateway (smspoint.de).
 *
 * Auth: X-Auth-Token header carrying a UUID generated/regenerated in the
 * SMSpoint panel.
 * Send: POST https://app.smspoint.de/public/api/v1/sms/send
 *       body { senderName, body, phone } — phone without leading "+".
 *       Response { "success": true } or { "success": false, "errorMessage": "..." }
 *       (HTTP 200 in both cases; no message ID returned).
 *
 * Capabilities the provider does NOT publicly document and which are therefore
 * intentionally absent: balance lookup, test-connection endpoint, DLR webhook
 * fields/signing, inbound MO, opt-out error codes, list-senders, templates,
 * and any non-SMS channel. The AbstractProvider defaults (getCredit() => null,
 * testConnection() => "not supported") are correct for this provider.
 */
class SmspointProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const ENDPOINT = 'https://app.smspoint.de/public/api/v1/sms/send';

    public function getId(): string
    {
        return 'smspoint';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_token' => [
                    'type'        => 'secret',
                    'label'       => __('API Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generated or regenerated in the SMSpoint panel — sent as the X-Auth-Token header.', 'wp-sms'),
                    'placeholder' => '00000000-0000-0000-0000-000000000000',
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender Name', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Alphanumeric sender ID — max 11 characters.', 'wp-sms'),
                        'placeholder' => 'Company ABC',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiToken = $this->getSharedConfig('api_token');
        $from = $this->getChannelConfig('sms', 'from');

        if (!$apiToken || !$from) {
            return DeliveryResult::failed(__('SMSpoint credentials not configured', 'wp-sms'));
        }

        $body = [
            'senderName' => $from,
            'body'       => $message->getBody(),
            'phone'      => ltrim($message->getRecipient(), '+'),
        ];

        $result = $this->httpPost(self::ENDPOINT, [
            'headers' => [
                'X-Auth-Token' => $apiToken,
                'Content-Type' => 'application/json;charset=UTF-8',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if (is_array($data) && ($data['success'] ?? false) === true) {
            return DeliveryResult::sent();
        }

        $errorMessage = is_array($data) ? ($data['errorMessage'] ?? null) : null;

        return DeliveryResult::failed(
            $errorMessage ?? sprintf(__('SMSpoint send failed (HTTP %d)', 'wp-sms'), $result['code']),
        );
    }
}

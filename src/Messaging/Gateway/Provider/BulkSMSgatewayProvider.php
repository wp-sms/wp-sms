<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * BulkSMSgateway — Hyderabad-based bulk SMS provider for Indian
 * businesses (bulksmsgateway.in).
 *
 * Send: GET https://www.bulksmsgateway.in/sendmessage.php
 *   Query: user, password, mobile (E.164 without leading +),
 *          message (URL-encoded), sender (DLT 6-char header),
 *          type (route id, default "3").
 *   Response: text/plain — undocumented. Numeric message id on
 *   success, or a free-text error string. The parser below is
 *   permissive: numeric body or success keyword → queued; anything
 *   else → failed with the body surfaced as the error.
 *
 * Auth: username + password as query params (the documented API).
 *
 * @todo verify — refine the response parser into a real status table
 *   once a real account captures literal success and failure bodies.
 * @todo whatsapp — bulksmsgateway.in sells a WhatsApp Business API
 *   product, but no public spec exists; defer until docs are public.
 * @todo callback — no documented DLR or inbound MO webhook.
 */
class BulkSMSgatewayProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_URL = 'https://www.bulksmsgateway.in/sendmessage.php';

    public function getId(): string
    {
        return 'bulksmsgateway';
    }

    public function getName(): string
    {
        return 'BulkSMSgateway';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'username' => [
                    'type'        => 'string',
                    'label'       => __('Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your BulkSMSgateway account username.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your BulkSMSgateway account password. Sent as a query parameter — use a dedicated API account if possible.', 'wp-sms'),
                ],
                'type' => [
                    'type'        => 'string',
                    'label'       => __('Route Type', 'wp-sms'),
                    'required'    => false,
                    'placeholder' => '3',
                    'description' => __('Route type from your BulkSMSgateway account manager (Promotional / Transactional). Leave blank to use "3".', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('DLT-approved 6-character alphanumeric header registered with TRAI.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');
        $sender   = $this->getChannelConfig('sms', 'sender_id');

        if (!$username || !$password) {
            return DeliveryResult::failed(__('BulkSMSgateway credentials not configured', 'wp-sms'));
        }
        if (!$sender) {
            return DeliveryResult::failed(__('BulkSMSgateway sender ID not configured', 'wp-sms'));
        }

        $url = self::SEND_URL . '?' . http_build_query([
            'user'     => $username,
            'password' => $password,
            'mobile'   => $message->getRecipient(),
            'message'  => $message->getBody(),
            'sender'   => $sender,
            'type'     => $this->getSharedConfig('type') ?: '3',
        ]);

        $result = $this->httpGet($url);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(sprintf('HTTP %d', $result['code']));
        }

        $body = trim((string) $result['body']);

        if ($body === '') {
            return DeliveryResult::failed(__('Empty response from BulkSMSgateway', 'wp-sms'));
        }

        if (preg_match('/^\d{6,}$/', $body)) {
            return DeliveryResult::queued($body);
        }

        if (preg_match('/\b(submit|success|sent|accepted|posted)/i', $body)) {
            return DeliveryResult::queued();
        }

        return DeliveryResult::failed($body, meta: ['bulksmsgateway_response' => $body]);
    }
}

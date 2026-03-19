<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Contracts\TestConnectionResult;

defined('ABSPATH') || exit;

class TwilioProvider extends AbstractProvider implements SupportsStatusCallback
{
    private const API_BASE = 'https://api.twilio.com/2010-04-01';

    public function getId(): string
    {
        return 'twilio';
    }

    public function getName(): string
    {
        return 'Twilio';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'account_sid' => [
                    'type'        => 'string',
                    'label'       => __('Account SID', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Account SID from the Twilio Console dashboard, starts with "AC"', 'wp-sms'),
                    'placeholder' => 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                ],
                'auth_token' => [
                    'type'        => 'secret',
                    'label'       => __('Auth Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Found below your Account SID on the Twilio Console dashboard', 'wp-sms'),
                    'placeholder' => '32-character hex string',
                ],
            ],
            'channels' => [
                'sms' => [
                    'from_number' => [
                        'type'        => 'string',
                        'label'       => __('From Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Your Twilio phone number in E.164 format (e.g., +15551234567)', 'wp-sms'),
                        'placeholder' => '+15551234567',
                    ],
                ],
                'whatsapp' => [
                    'from_number' => [
                        'type'        => 'string',
                        'label'       => __('WhatsApp Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Your Twilio WhatsApp-enabled number. For sandbox testing, use +14155238886', 'wp-sms'),
                        'placeholder' => '+14155238886',
                    ],
                ],
            ],
        ];
    }

    public function getMetadata(): array
    {
        return [
            'description' => __('Cloud communications platform for SMS, WhatsApp, and more', 'wp-sms'),
            'website'     => 'https://www.twilio.com',
            'icon'        => '',
            'regions'     => ['global'],
            'setup_url'   => 'https://console.twilio.com/',
            'setup_notes' => [
                __('Find your Account SID and Auth Token on the Twilio Console dashboard.', 'wp-sms'),
                __('For SMS, purchase a phone number at Phone Numbers > Manage > Buy a Number.', 'wp-sms'),
                __('For WhatsApp, enable the sandbox at Messaging > Try it out > Send a WhatsApp message.', 'wp-sms'),
            ],
        ];
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'mms'              => true,
            'delivery_receipt' => true,
            'incoming'         => true,
        ]);
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $accountSid = $this->getSharedConfig('account_sid');
        $authToken = $this->getSharedConfig('auth_token');
        $channel = $message->getChannel();
        $from = $this->getChannelConfig($channel, 'from_number');

        if (!$accountSid || !$authToken || !$from) {
            return DeliveryResult::failed(__('Twilio credentials not configured', 'wp-sms'));
        }

        $to = $message->getRecipient();
        // WhatsApp requires the whatsapp: prefix
        if ($channel === 'whatsapp') {
            $from = 'whatsapp:' . $from;
            $to = 'whatsapp:' . $to;
        }

        $url = self::API_BASE . "/Accounts/{$accountSid}/Messages.json";

        $body = [
            'From'           => $from,
            'To'             => $to,
            'Body'           => $message->getBody(),
            'StatusCallback' => $this->getStatusCallbackUrl(),
        ];

        $mediaUrls = $message->getMeta()['media_urls'] ?? [];
        foreach ($mediaUrls as $mediaUrl) {
            $body['MediaUrl'][] = $mediaUrl;
        }

        $result = $this->httpPost($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("{$accountSid}:{$authToken}"),
            ],
            'body' => $body,
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] >= 200 && $result['code'] < 300) {
            $status = $data['status'] ?? 'queued';
            $providerId = $data['sid'] ?? null;
            $cost = isset($data['price']) ? abs((float) $data['price']) : null;

            if (in_array($status, ['sent', 'delivered'], true)) {
                return DeliveryResult::sent($providerId, $cost);
            }

            return DeliveryResult::queued($providerId);
        }

        return DeliveryResult::failed(
            $data['message'] ?? "HTTP {$result['code']}",
            meta: array_filter([
                'twilio_code' => $data['code'] ?? null,
                'more_info'   => $data['more_info'] ?? null,
            ]),
        );
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        $authToken = $this->getSharedConfig('auth_token');
        if (!$authToken) {
            return false;
        }

        $signature = $request->get_header('x-twilio-signature');
        if (empty($signature)) {
            return false;
        }

        $url = $this->getStatusCallbackUrl();
        $params = $request->get_params();
        unset($params['gateway_id']);

        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key . $value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        return hash_equals($expected, $signature);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $messageSid = $request->get_param('MessageSid');
        $messageStatus = $request->get_param('MessageStatus');

        if (empty($messageSid) || empty($messageStatus)) {
            return [];
        }

        $status = match ($messageStatus) {
            'queued', 'accepted' => 'queued',
            'sending', 'sent'   => 'sent',
            'delivered'         => 'delivered',
            'undelivered', 'failed' => 'failed',
            default             => $messageStatus,
        };

        return [new StatusUpdate(
            providerId: $messageSid,
            status: $status,
            errorCode: $request->get_param('ErrorCode'),
            errorMessage: $request->get_param('ErrorMessage'),
        )];
    }

    public function getStatusCallbackUrl(): string
    {
        return rest_url('wsms/v1/callbacks/twilio/status');
    }

    public function getCredit(): ?string
    {
        $accountSid = $this->getSharedConfig('account_sid');
        $authToken = $this->getSharedConfig('auth_token');

        if (!$accountSid || !$authToken) {
            return null;
        }

        $url = self::API_BASE . "/Accounts/{$accountSid}/Balance.json";

        $result = $this->httpGet($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("{$accountSid}:{$authToken}"),
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);

        $balance = $data['balance'] ?? null;
        if ($balance === null) {
            return null;
        }
        $currency = $data['currency'] ?? 'USD';
        return "{$balance} {$currency}";
    }

    public function testConnection(): TestConnectionResult
    {
        $accountSid = $this->getSharedConfig('account_sid');
        $authToken = $this->getSharedConfig('auth_token');

        if (!$accountSid || !$authToken) {
            return TestConnectionResult::error(__('Account SID and Auth Token are required', 'wp-sms'));
        }

        $url = self::API_BASE . "/Accounts/{$accountSid}/Balance.json";

        $result = $this->httpGet($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("{$accountSid}:{$authToken}"),
            ],
        ]);

        // Provider-specific error codes before common validation
        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401) {
                return TestConnectionResult::error(__('Invalid Account SID or Auth Token', 'wp-sms'));
            }
            if ($result['code'] === 404) {
                return TestConnectionResult::error(__('Account not found — check your Account SID', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Twilio');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = $data['balance'] ?? 'N/A';
        $currency = $data['currency'] ?? 'USD';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s %s', 'wp-sms'), $balance, $currency),
            ['balance' => $balance, 'currency' => $currency],
        );
    }
}

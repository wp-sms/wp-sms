<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

class GatewaySaProvider extends AbstractProvider
{
    public const TESTED = false;

    private const API_BASE = 'http://rest.gateway.sa/api';

    public function getId(): string
    {
        return 'gateway';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_id' => [
                    'type'        => 'string',
                    'label'       => __('API ID', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Gateway.sa account API ID, available in the customer portal at client.gateway.sa.', 'wp-sms'),
                ],
                'api_password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Gateway.sa API password, paired with the API ID above.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('CITC-approved alphanumeric sender ID registered with Gateway.sa support.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiId       = $this->getSharedConfig('api_id');
        $apiPassword = $this->getSharedConfig('api_password');
        $senderId    = $this->getChannelConfig('sms', 'sender_id');

        if (!$apiId || !$apiPassword || !$senderId) {
            return DeliveryResult::failed(__('Gateway.sa credentials not configured', 'wp-sms'));
        }

        $body     = $message->getBody();
        $encoding = mb_check_encoding($body, 'ASCII') ? 'T' : 'U';

        $params = [
            'api_id'       => $apiId,
            'api_password' => $apiPassword,
            'sms_type'     => 'T',
            'encoding'     => $encoding,
            'sender_id'    => $senderId,
            'phonenumber'  => $message->getRecipient(),
            'textmessage'  => $body,
        ];

        // TODO(templates): Gateway.sa accepts a `template_id` query param.
        // v7 surfaced it via a `|template_id` body-parsing UX hack, but the
        // server-side semantics (compliance tag vs. approved-content
        // reference) aren't in any accessible doc. Defer SupportsTemplates +
        // catalog wiring until verified against the official PDF or live API.
        $meta = $message->getMeta();
        if (!empty($meta['provider_template_id'])) {
            $params['template_id'] = $meta['provider_template_id'];
        }

        // TODO(verify): Gateway.sa does NOT expose a Verify-as-a-Service
        // endpoint per its public marketing — OTP delivery flows through the
        // standard /SendSMSMulti path. No deferral needed beyond the
        // SupportsTemplates work above.

        $result = $this->httpGet(self::API_BASE . '/SendSMSMulti?' . http_build_query($params));

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(sprintf('HTTP %d', $result['code']));
        }

        $data = json_decode($result['body'], true);

        if (is_array($data) && ($data['status'] ?? null) === 'F') {
            return DeliveryResult::failed($data['remarks'] ?? __('Gateway.sa send failed', 'wp-sms'));
        }

        $providerId = is_array($data) ? (string) ($data['MessageID'] ?? '') : '';

        return DeliveryResult::sent(providerId: $providerId);
    }

    public function getCredit(): ?string
    {
        $apiId       = $this->getSharedConfig('api_id');
        $apiPassword = $this->getSharedConfig('api_password');

        if (!$apiId || !$apiPassword) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/CheckBalance?' . http_build_query([
            'api_id'       => $apiId,
            'api_password' => $apiPassword,
        ]));

        if ($result instanceof DeliveryResult) {
            return null;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);

        if (!is_array($data) || !isset($data['BalanceAmount'])) {
            return null;
        }

        return (string) $data['BalanceAmount'];
    }

    public function testConnection(): TestConnectionResult
    {
        $apiId       = $this->getSharedConfig('api_id');
        $apiPassword = $this->getSharedConfig('api_password');

        if (!$apiId || !$apiPassword) {
            return TestConnectionResult::error(__('API ID and API Password are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/CheckBalance?' . http_build_query([
            'api_id'       => $apiId,
            'api_password' => $apiPassword,
        ]));

        $data = $this->validateTestResponse($result, 'Gateway.sa');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (isset($data['BalanceAmount'])) {
            $balance = (string) $data['BalanceAmount'];
            return TestConnectionResult::ok(
                sprintf(__('Connected — Balance: %s', 'wp-sms'), $balance),
                ['balance' => $balance],
            );
        }

        $message = $data['remarks'] ?? __('Invalid credentials', 'wp-sms');
        return TestConnectionResult::error($message);
    }
}

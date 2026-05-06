<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * MatinSMS — Iranian SMS reseller riding on the Kavenegar REST API.
 *
 * MatinSMS issues Kavenegar API keys to its customers, so the v1 plugin pointed
 * the gateway at the same kavenegar.com endpoints used by KavenegarProvider:
 *   Send:   GET https://api.kavenegar.com/v1/{api_key}/sms/send.json
 *           ?receptor={to}&sender={from}&message={text}
 *   Credit: GET https://api.kavenegar.com/v1/{api_key}/account/info.json
 *
 * Auth: API key embedded in the URL path.
 * Response shape: { return: { status, message }, entries } — status == 200 is success.
 *
 * Out of scope (not exposed by the v1 integration): Kavenegar's verify/lookup
 * (template / OTP) endpoint — MatinSMS users who need pattern messages should
 * configure the Kavenegar provider directly. MMS, status webhooks, inbound
 * webhooks, and opt-out detection are also out of scope here.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class MatinSmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.kavenegar.com/v1';

    public function getId(): string
    {
        return 'matinsms';
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
                    'description' => __('Your MatinSMS API key (Kavenegar-compatible)', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the MatinSMS panel', 'wp-sms'),
                        'placeholder' => '10001234567890',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return DeliveryResult::failed(__('MatinSMS API key not configured', 'wp-sms'));
        }

        $sender = (string) $this->getChannelConfig('sms', 'sender', '');
        if ($sender === '') {
            return DeliveryResult::failed(__('MatinSMS sender not configured', 'wp-sms'));
        }

        $url = sprintf('%s/%s/sms/send.json', self::API_BASE, rawurlencode($apiKey));
        $url .= '?' . http_build_query([
            'receptor' => $message->getRecipient(),
            'sender'   => $sender,
            'message'  => $message->getBody(),
        ]);

        $result = $this->httpGet($url);

        return $this->parseResponse($result);
    }

    private function parseResponse(array|DeliveryResult $result): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(__('Invalid response from MatinSMS', 'wp-sms'));
        }

        $status = (int) ($data['return']['status'] ?? 0);
        if ($status === 200) {
            $entry = $data['entries'][0] ?? [];
            return DeliveryResult::sent(
                providerId: (string) ($entry['messageid'] ?? ''),
                cost: isset($entry['cost']) ? (float) $entry['cost'] : null,
            );
        }

        $message = (string) ($data['return']['message'] ?? __('MatinSMS send failed', 'wp-sms'));
        return DeliveryResult::failed($message);
    }

    public function getCredit(): ?string
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return null;
        }

        $url = sprintf('%s/%s/account/info.json', self::API_BASE, rawurlencode($apiKey));
        $result = $this->httpGet($url);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || (int) ($data['return']['status'] ?? 0) !== 200) {
            return null;
        }

        $credit = $data['entries']['remaincredit'] ?? null;
        return $credit === null ? null : (string) $credit;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $url = sprintf('%s/%s/account/info.json', self::API_BASE, rawurlencode($apiKey));
        $result = $this->httpGet($url);

        $data = $this->validateTestResponse($result, 'MatinSMS');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $status = (int) ($data['return']['status'] ?? 0);
        if ($status !== 200) {
            $message = (string) ($data['return']['message'] ?? __('Unknown error', 'wp-sms'));
            return TestConnectionResult::error($message);
        }

        $credit = (string) ($data['entries']['remaincredit'] ?? 'N/A');
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }
}

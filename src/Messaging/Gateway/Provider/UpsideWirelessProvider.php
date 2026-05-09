<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Upside Wireless — Vancouver-based SMS aggregator (REST v1).
 *
 * Auth is a token + signature pair pulled from the reseller dashboard. v1
 * re-fetched both via Authentication.asmx/GetParameters on every send, which
 * the provider's own wiki warns can get the customer's IP blocked — this v2
 * port stores both directly and skips the per-send fetch entirely.
 *
 * Send: POST /RESTv1/{token}/Message  body={signature,type=sms,recipient,
 *       message,encoding=16}. Always UCS-2 (encoding=16) to maximise charset
 *       coverage; per-message cost difference is acceptable for this gateway.
 * Test: same endpoint with type=sms_test — wiki guarantees no credit consumption
 *       and Status="PASSED" on success.
 *
 * No getCredit() override: REST has no balance endpoint and the SOAP
 * Account.asmx/Balance_Get is HTTP-only. AbstractProvider's null default is
 * the right behaviour — don't re-litigate.
 *
 * Out of scope (clean follow-ups if requested): MMS (type=mms + mediaUrl),
 * inbound MO + DLR webhooks (no documented signature scheme; registration is
 * out-of-band via Upside support), flash SMS, and verify-as-a-service.
 */
class UpsideWirelessProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://secureapi.upsidewireless.com/RESTv1';

    public function getId(): string
    {
        return 'upsidewireless';
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
                    'type'        => 'string',
                    'label'       => __('API Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('UUID-style token from your Upside Wireless reseller dashboard account page.', 'wp-sms'),
                ],
                'signature' => [
                    'type'        => 'secret',
                    'label'       => __('API Signature', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Signature value from the same account page; rotates if you change your dashboard password.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $token     = (string) $this->getSharedConfig('token', '');
        $signature = (string) $this->getSharedConfig('signature', '');

        if ($token === '' || $signature === '') {
            return DeliveryResult::failed(__('Upside Wireless credentials not configured', 'wp-sms'));
        }

        $result = $this->httpPost($this->endpoint($token), [
            'body' => [
                'signature' => $signature,
                'type'      => 'sms',
                'recipient' => ltrim($message->getRecipient(), '+'),
                'message'   => $message->getBody(),
                'encoding'  => '16',
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401) {
            return DeliveryResult::failed(__('Invalid Upside Wireless API Token or Signature', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(sprintf('HTTP %d', $result['code']));
        }

        if (!empty($data['HasException'])) {
            return DeliveryResult::failed($this->formatErrorMessage($data));
        }

        $sms = $data['SMSMessage'] ?? [];
        $status = isset($sms['Status']) ? (string) $sms['Status'] : '';

        if ($status === 'QUEUED') {
            $providerId = isset($sms['TrackingId']) ? (string) $sms['TrackingId'] : null;
            return DeliveryResult::queued($providerId);
        }

        $reason = (string) ($sms['RejectReason'] ?? $status ?? __('Unknown error', 'wp-sms'));
        return DeliveryResult::failed(sprintf(__('Upside Wireless rejected the message: %s', 'wp-sms'), $reason));
    }

    public function testConnection(): TestConnectionResult
    {
        $token     = (string) $this->getSharedConfig('token', '');
        $signature = (string) $this->getSharedConfig('signature', '');

        if ($token === '' || $signature === '') {
            return TestConnectionResult::error(__('API Token and Signature are required', 'wp-sms'));
        }

        $result = $this->httpPost($this->endpoint($token), [
            'body' => [
                'signature' => $signature,
                'type'      => 'sms_test',
                'recipient' => '10000000000',
                'message'   => 'test',
                'encoding'  => '16',
            ],
        ]);

        if (!$result instanceof DeliveryResult && $result['code'] === 401) {
            return TestConnectionResult::error(__('Invalid Upside Wireless API Token or Signature', 'wp-sms'));
        }

        $data = $this->validateTestResponse($result, 'Upside Wireless');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (!empty($data['HasException'])) {
            return TestConnectionResult::error($this->formatErrorMessage($data));
        }

        $status = (string) ($data['SMSMessage']['Status'] ?? '');
        if ($status === 'PASSED') {
            return TestConnectionResult::ok(__('Connected to Upside Wireless', 'wp-sms'));
        }

        return TestConnectionResult::error(sprintf(__('Unexpected response from Upside Wireless: %s', 'wp-sms'), $status !== '' ? $status : __('empty status', 'wp-sms')));
    }

    private function endpoint(string $token): string
    {
        return self::API_BASE . '/' . rawurlencode($token) . '/Message?responsetype=JSON';
    }

    private function formatErrorMessage(array $data): string
    {
        $code = $data['ErrorCode'] ?? null;
        $msg  = (string) ($data['ErrorMessage'] ?? '');

        if ($msg === '' && $code === null) {
            return __('Upside Wireless API error', 'wp-sms');
        }

        if ($code !== null && $msg !== '') {
            return sprintf('%s (code %s)', $msg, (string) $code);
        }

        return $msg !== '' ? $msg : sprintf(__('Upside Wireless error code %s', 'wp-sms'), (string) $code);
    }
}

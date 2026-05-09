<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * EVERY8D — Taiwan-domestic premium SMS gateway operated by Teamplus Technology.
 *
 * Auth: form-encoded UID/PWD on every request (no API tokens).
 * Send: POST /sendSMS.ashx — CSV "Credit,Sent,Cost,Unsent,BatchID" on success,
 *       leading negative credit (e.g. "-1,Authentication failure") on error.
 * Credit: POST /getCredit.ashx — CSV "Credit,Message".
 * Sender ID: provisioned by Teamplus support; not configurable via the API.
 */
// TODO(verify): EVERY8D markets an OTP product but the public HTTP API doesn't
// expose a Verify-as-a-Service endpoint; revisit when SupportsVerify lands.
class Every8dProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_URL   = 'https://oms.e8d.tw/API21/HTTP/sendSMS.ashx';
    private const CREDIT_URL = 'https://oms.e8d.tw/API21/HTTP/getCredit.ashx';

    public function getId(): string
    {
        return 'every8d';
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
                    'label'       => __('Username (UID)', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your EVERY8D account UID from the oms.e8d.tw portal.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password (PWD)', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your EVERY8D account password.', 'wp-sms'),
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return DeliveryResult::failed(__('EVERY8D credentials not configured', 'wp-sms'));
        }

        $params = [
            'UID'  => $username,
            'PWD'  => $password,
            'SB'   => '',
            'MSG'  => $message->getBody(),
            'DEST' => $message->getRecipient(),
        ];

        $result = $this->httpPost(self::SEND_URL, ['body' => $params]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(sprintf('HTTP %d', $result['code']));
        }

        $body = trim($result['body']);
        $parts = explode(',', $body);
        $credit = $parts[0] ?? '';

        if ($credit === '' || $credit[0] === '-') {
            $errorMessage = trim($parts[1] ?? '');
            return DeliveryResult::failed(
                $errorMessage !== '' ? $errorMessage : __('EVERY8D rejected the request', 'wp-sms')
            );
        }

        // "Credit,Sent,Cost,Unsent,BatchID"
        $batchId = trim($parts[4] ?? '');

        return DeliveryResult::sent(providerId: $batchId !== '' ? $batchId : null);
    }

    public function getCredit(): ?string
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return null;
        }

        $result = $this->httpPost(self::CREDIT_URL, [
            'body' => ['UID' => $username, 'PWD' => $password],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $body = trim($result['body']);
        $parts = explode(',', $body);
        $credit = $parts[0] ?? '';

        if ($credit === '' || $credit[0] === '-') {
            return null;
        }

        return $credit;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return TestConnectionResult::error(__('Username and password are required', 'wp-sms'));
        }

        $result = $this->httpPost(self::CREDIT_URL, [
            'body' => ['UID' => $username, 'PWD' => $password],
        ]);

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the EVERY8D API. Check your server\'s internet connection.', 'wp-sms')
            );
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from EVERY8D (HTTP %d)', 'wp-sms'), $result['code'])
            );
        }

        $body = trim($result['body']);
        $parts = explode(',', $body);
        $credit = $parts[0] ?? '';

        if ($credit === '' || $credit[0] === '-') {
            $errorMessage = trim($parts[1] ?? '');
            return TestConnectionResult::error(
                $errorMessage !== '' ? $errorMessage : __('Invalid EVERY8D username or password', 'wp-sms')
            );
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }
}

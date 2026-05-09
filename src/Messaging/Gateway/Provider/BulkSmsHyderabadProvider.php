<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Bulk SMS Hyderabad — India-only premium SMS reseller
 * (bulksmshyderabad.co.in). The provider has no public API documentation;
 * the request shape below is ported verbatim from the v7 legacy plugin
 * (`class-wpsms-pro-gateway-bulksmshyderabad.php`), which is the only
 * authoritative source for the field names and success semantics.
 *
 * Send: GET http://tra.bulksmshyderabad.co.in/websms/sendsms.aspx
 *   Query: userid, password, sender (DLT-approved 6-char header),
 *          mobileno (E.164 without leading +), msg.
 *   Response: text/plain — undocumented. v7 treats any HTTP 200 as success
 *   and surfaces the body as the operation result.
 *
 * Auth: userid + password as query params.
 */
class BulkSmsHyderabadProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_URL = 'http://tra.bulksmshyderabad.co.in/websms/sendsms.aspx';

    public function getId(): string
    {
        return 'bulksmshyderabad';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'userid' => [
                    'type'        => 'string',
                    'label'       => __('User ID', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Bulk SMS Hyderabad account User ID.', 'wp-sms'),
                    'placeholder' => __('Your User ID', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Bulk SMS Hyderabad account password.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Your DLT-approved 6-character alphanumeric Sender ID.', 'wp-sms'),
                        'placeholder' => 'MYBRND',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $userid   = $this->getSharedConfig('userid');
        $password = $this->getSharedConfig('password');
        $sender   = $this->getChannelConfig('sms', 'sender');

        if (!$userid || !$password || !$sender) {
            return DeliveryResult::failed(__('Bulk SMS Hyderabad credentials not configured', 'wp-sms'));
        }

        $url = self::API_URL . '?' . http_build_query([
            'userid'   => $userid,
            'password' => $password,
            'sender'   => $sender,
            'mobileno' => $message->getRecipient(),
            'msg'      => $message->getBody(),
        ]);

        $result = $this->httpGet($url);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(sprintf(
                __('Bulk SMS Hyderabad error (HTTP %d): %s', 'wp-sms'),
                $result['code'],
                trim((string) $result['body'])
            ));
        }

        // v7 treats any HTTP 200 as success and returns the response body. No
        // documented success/error grammar exists, so we mirror that here and
        // surface the body as the provider's message ID for debugging.
        $body = trim((string) $result['body']);

        return DeliveryResult::sent(providerId: $body !== '' ? $body : null);
    }

    public function testConnection(): TestConnectionResult
    {
        $userid   = $this->getSharedConfig('userid');
        $password = $this->getSharedConfig('password');
        $sender   = $this->getChannelConfig('sms', 'sender');

        if (!$userid || !$password || !$sender) {
            return TestConnectionResult::error(__('User ID, Password, and Sender ID are required', 'wp-sms'));
        }

        // Hit the send endpoint with an invalid mobile so nothing is actually
        // delivered — this validates host reachability and that the gateway
        // accepts our credentials. The response body is what users debug
        // against, so we surface it verbatim.
        $url = self::API_URL . '?' . http_build_query([
            'userid'   => $userid,
            'password' => $password,
            'sender'   => $sender,
            'mobileno' => '0',
            'msg'      => 'test',
        ]);

        $result = $this->httpGet($url);

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(__('Could not reach the Bulk SMS Hyderabad API. Check your server\'s internet connection.', 'wp-sms'));
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from Bulk SMS Hyderabad (HTTP %d)', 'wp-sms'), $result['code'])
            );
        }

        $body = trim((string) $result['body']);

        return TestConnectionResult::ok(
            sprintf(__('Reachable — gateway response: %s', 'wp-sms'), $body !== '' ? $body : __('(empty)', 'wp-sms'))
        );
    }
}

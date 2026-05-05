<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Mobiledotnet — Saudi SMS gateway, traded as "MadarSMS" by Orbittec
 * (corbit.sa marketing, app.mobile.net.sa dashboard).
 *
 * Send: GET https://mobile.net.sa/sms/gw/
 *   Query: userName, userPassword, numbers (CSV, MSISDN no leading +),
 *          userSender, msg (URL-encoded UTF-8), By (route id, default "standard").
 *   Response: text/html body — numeric string. "1" = success; any other
 *   code maps via describeError().
 *
 * Credit / test connection: GET https://mobile.net.sa/sms/gw/Credits.php
 *   Same userName/userPassword/By; response is the numeric balance, or
 *   one of the same error codes.
 *
 * Auth: username + password as query params. Yes, the credentials are in
 * the URL — that is the documented API. No SDK or HMAC layer exists.
 *
 * @todo whatsapp — corbit.sa markets a WhatsApp product but no public API
 *   endpoint is documented; the dashboard's /api-doc page is gated behind
 *   account login. Add a 'whatsapp' channel only after the spec is captured.
 * @todo callback — no documented DLR or inbound MO webhook surface.
 * @todo dynamic-sender-list — no public getSenders endpoint; sender IDs
 *   are entered manually after CITC approval.
 */
class MobiledotnetProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://mobile.net.sa/sms/gw';

    public function getId(): string
    {
        return 'mobiledotnet';
    }

    public function getName(): string
    {
        return 'MadarSMS (Mobiledotnet)';
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
                    'description' => __('Your MadarSMS account username from app.mobile.net.sa.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your MadarSMS account password. Sent as a query parameter — use a dedicated API account if possible.', 'wp-sms'),
                ],
                'route_id' => [
                    'type'        => 'string',
                    'label'       => __('Route ID', 'wp-sms'),
                    'required'    => false,
                    'placeholder' => 'standard',
                    'description' => __('Routing tier (the "By" parameter). Leave blank to use "standard"; premium accounts may have other routes.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('CITC-approved alphanumeric sender ID registered with MadarSMS support.', 'wp-sms'),
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
            return DeliveryResult::failed(__('Mobiledotnet credentials not configured', 'wp-sms'));
        }
        if (!$sender) {
            return DeliveryResult::failed(__('Mobiledotnet sender ID not configured', 'wp-sms'));
        }

        $url = self::API_BASE . '/?' . http_build_query([
            'userName'     => $username,
            'userPassword' => $password,
            'numbers'      => $message->getRecipient(),
            'userSender'   => $sender,
            'msg'          => $message->getBody(),
            'By'           => $this->getSharedConfig('route_id') ?: 'standard',
        ]);

        $result = $this->httpGet($url);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(sprintf('HTTP %d', $result['code']));
        }

        $body = trim((string) $result['body']);

        if ($body === '1') {
            return DeliveryResult::sent();
        }

        $error = $this->describeError($body) ?? sprintf(__('Mobiledotnet send failed (code %s)', 'wp-sms'), $body);
        return DeliveryResult::failed($error, meta: ['mobiledotnet_code' => $body]);
    }

    public function getCredit(): ?string
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/Credits.php?' . http_build_query([
            'userName'     => $username,
            'userPassword' => $password,
            'By'           => $this->getSharedConfig('route_id') ?: 'standard',
        ]));

        if ($result instanceof DeliveryResult || $result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $body = trim((string) $result['body']);

        if ($body === '' || $this->describeError($body) !== null) {
            return null;
        }

        return $body;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return TestConnectionResult::error(__('Username and Password are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/Credits.php?' . http_build_query([
            'userName'     => $username,
            'userPassword' => $password,
            'By'           => $this->getSharedConfig('route_id') ?: 'standard',
        ]));

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the Mobiledotnet API. Check your server\'s internet connection.', 'wp-sms')
            );
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from Mobiledotnet (HTTP %d)', 'wp-sms'), $result['code'])
            );
        }

        $body = trim((string) $result['body']);
        $error = $this->describeError($body);
        if ($error !== null) {
            return TestConnectionResult::error($error);
        }

        if ($body === '') {
            return TestConnectionResult::error(__('Invalid response from Mobiledotnet', 'wp-sms'));
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $body),
            ['credit' => $body],
        );
    }

    /**
     * Map a Mobiledotnet numeric response to a localised error message.
     * Returns null for the success code "1" or any unknown value.
     * Codes ported verbatim from WSMS commit 5e560c4c
     * (includes/gateways/mobiledotnet.class.php).
     */
    private function describeError(string $code): ?string
    {
        switch ($code) {
            case '1':    return null;
            case '0':    return __('Incomplete information (username or password).', 'wp-sms');
            case '00':   return __('Username or password is empty.', 'wp-sms');
            case '000':  return __('Wrong data entry.', 'wp-sms');
            case '0000': return __('Account balance is 0.', 'wp-sms');
            case '1010': return __('Incomplete information (username, password, phone number, or message body).', 'wp-sms');
            case '1020': return __('Invalid login.', 'wp-sms');
            case '1030': return __('Duplicate message in queue — wait 10 seconds before resending.', 'wp-sms');
            case '1040': return __('Unrecognised characters in message body.', 'wp-sms');
            case '1050': return __('Message body is empty.', 'wp-sms');
            case '1060': return __('Account balance is not enough to send the message.', 'wp-sms');
            case '1070': return __('Account balance is 0.', 'wp-sms');
            case '1080': return __('Send error — message not delivered.', 'wp-sms');
            case '1090': return __('Duplicate send detected.', 'wp-sms');
            case '1100': return __('Send failed — please try again later.', 'wp-sms');
            case '1110': return __('Sender name is incorrect.', 'wp-sms');
            case '1120': return __('Destination country is not covered.', 'wp-sms');
            case '1130': return __('Account is restricted by the network supervisor.', 'wp-sms');
            case '1140': return __('Message has too many parts.', 'wp-sms');
            case '1150': return __('Duplicate message (same number, sender, and body).', 'wp-sms');
            case '1160': return __('Invalid scheduled-send datetime.', 'wp-sms');
            default:     return null;
        }
    }
}

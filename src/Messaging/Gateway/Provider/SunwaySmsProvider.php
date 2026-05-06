<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Sunway SMS — Iranian SMS gateway with a flat HTTP/GET REST API
 * (HttpService.ashx style endpoint).
 *
 * Endpoints (action selected via the `service` query parameter):
 *   Send:   GET http://sms.sunwaysms.com/smsws/HttpService.ashx
 *           ?service=SendArray&UserName=&Password=&To=&Message=&From=&Flash=
 *   Credit: GET http://sms.sunwaysms.com/smsws/HttpService.ashx
 *           ?service=GetCredit&username=&password=
 *
 * Auth: account username + password as query-string parameters.
 *
 * Response: opaque text body. The send endpoint returns either a numeric
 * message id or a numeric error code (50 = success, anything else maps to
 * a documented error label — see mapErrorCode()).
 *
 * Out of scope: templates/patterns (not exposed by this REST API), MMS, flash
 * SMS as a meta channel, status webhooks, inbound webhooks.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class SunwaySmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'http://sms.sunwaysms.com/smsws/HttpService.ashx';

    public function getId(): string
    {
        return 'sunwaysms';
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
                    'label'       => __('API Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Sunway SMS panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Sunway SMS panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the Sunway SMS panel', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('Sunway SMS credentials not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('Sunway SMS sender not configured', 'wp-sms'));
        }

        $url = self::API_BASE . '?' . http_build_query([
            'service'  => 'SendArray',
            'UserName' => $username,
            'Password' => $password,
            'To'       => $message->getRecipient(),
            'Message'  => $message->getBody(),
            'From'     => $sender,
            'Flash'    => 'false',
        ]);

        $result = $this->httpGet($url);
        return $this->parseResponse($result);
    }

    private function parseResponse(array|DeliveryResult $result): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(
                sprintf(__('Sunway SMS send failed (HTTP %d)', 'wp-sms'), $result['code'])
            );
        }

        $body = trim((string) $result['body']);
        if ($body === '') {
            return DeliveryResult::failed(__('Empty response from Sunway SMS', 'wp-sms'));
        }

        // Sunway returns numeric codes; 50 = "Successful" — anything else maps to a labelled error.
        if (is_numeric($body)) {
            $code = (int) $body;
            if ($code === 50) {
                return DeliveryResult::sent($body);
            }

            $label = $this->mapErrorCode($body);
            return DeliveryResult::failed($label !== null ? $label : sprintf(__('Sunway SMS error code %s', 'wp-sms'), $body));
        }

        // Non-numeric body — assume it's a message id (some Sunway endpoints return alphanumeric ids).
        return DeliveryResult::sent($body);
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        if ($username === '' || $password === '') {
            return null;
        }

        $url = self::API_BASE . '?' . http_build_query([
            'service'  => 'GetCredit',
            'username' => $username,
            'password' => $password,
        ]);

        $result = $this->httpGet($url);
        if ($result instanceof DeliveryResult) {
            return null;
        }
        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $credit = trim((string) $result['body']);
        return $credit !== '' ? $credit : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        $url = self::API_BASE . '?' . http_build_query([
            'service'  => 'GetCredit',
            'username' => $username,
            'password' => $password,
        ]);

        $result = $this->httpGet($url);
        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the Sunway SMS API. Check your server\'s internet connection.', 'wp-sms')
            );
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from Sunway SMS (HTTP %d)', 'wp-sms'), $result['code'])
            );
        }

        $credit = trim((string) $result['body']);
        if ($credit === '') {
            return TestConnectionResult::error(__('Unknown error', 'wp-sms'));
        }

        // Some Sunway error responses come back as numeric codes — treat those as failures.
        if (is_numeric($credit) && (int) $credit < 50) {
            $label = $this->mapErrorCode($credit);
            return TestConnectionResult::error($label !== null ? $label : sprintf(__('Sunway SMS error code %s', 'wp-sms'), $credit));
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }

    /**
     * Map Sunway SMS numeric response codes to human-readable labels.
     *
     * @return string|null Label, or null if the code is unknown.
     */
    private function mapErrorCode(string $code): ?string
    {
        $map = [
            '0'  => 'MessageIDIsInvalid',
            '1'  => 'PendingStatus',
            '2'  => 'DeliveredToPhone',
            '3'  => 'FailedToPhone',
            '4'  => 'DeliveredToServiceCenter',
            '5'  => 'FailedToServiceCenter',
            '6'  => 'InDisableList',
            '7'  => 'InSendQueue',
            '8'  => 'Sending',
            '9'  => 'LowCredit',
            '10' => 'NotSent',
            '50' => 'Successful',
            '51' => 'UserNameOrPasswordIsWrong',
            '52' => 'UserNameOrPasswordIsEmpty',
            '53' => 'RecipientNumberLengthIsMoreThanUsual',
            '54' => 'RecipientNumberIsEmpty',
            '55' => 'RecipientNumberIsNull',
            '56' => 'MessageIDLengthIsMoreThanUsual',
            '57' => 'MessageIDIsEmpty',
            '58' => 'MessageIDIsNull',
            '59' => 'MessageBodyIsEmpty',
            '60' => 'InThisTimeServerCannotRespond',
            '61' => 'SpecialNumberIsInvalid',
            '62' => 'SpecialNumberIsEmpty',
            '63' => 'ThisIPIsInvalid',
            '64' => 'WSIDIsWrong',
            '65' => 'NumberOfMessageIsWrong',
            '66' => 'CheckingMessageIDLengthIsNotEqualWithRecipientNumberLength',
            '67' => 'CheckingMessageIDLengthIsMoreThanUsual',
            '68' => 'CheckingMessageIDIsEmpty',
            '69' => 'CheckingMessageIDIsNull',
        ];

        return $map[$code] ?? null;
    }
}

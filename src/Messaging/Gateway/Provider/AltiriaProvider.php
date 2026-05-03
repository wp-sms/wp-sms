<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Altiria — Spanish SMS gateway with REST API for transactional and bulk
 * SMS delivery across Europe and Latin America.
 *
 * API contract verified against the official PHP SDK
 * (github.com/altiria/sms-php-client) and the public docs:
 *   Send:   POST https://www.altiria.net:8443/apirest/ws/sendSms
 *   Credit: POST https://www.altiria.net:8443/apirest/ws/getCredit
 *   Auth:   { credentials: { login, passwd } } in JSON body.
 *   Body:   { credentials, destination: ["3466…"], message: { msg, senderId?,
 *             concat?, encoding? }, source }
 *
 * Success envelope: HTTP 200 + { status: "000" }; getCredit also returns
 * { credit: "12.5" }.
 *
 * Out of scope (not in the SDK): DLR webhook, inbound MO, templates,
 * list-senders, scheduleDate, certified SMS (certDelivery), the apikey/
 * apisecret alt auth mode, and WhatsApp Business (commercial-only, no
 * public REST surface).
 */
class AltiriaProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE        = 'https://www.altiria.net:8443/apirest/ws';
    private const SEND_ENDPOINT   = self::API_BASE . '/sendSms';
    private const CREDIT_ENDPOINT = self::API_BASE . '/getCredit';
    private const SOURCE          = 'wp-sms';

    public function getId(): string
    {
        return 'altiria';
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
                    'required'    => true,
                    'label'       => __('Username', 'wp-sms'),
                    'description' => __('Account login email registered with Altiria.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'required'    => true,
                    'label'       => __('Password', 'wp-sms'),
                    'description' => __('Altiria account password.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'required'    => false,
                        'label'       => __('Sender ID', 'wp-sms'),
                        'placeholder' => 'MyBrand',
                        'description' => __('Optional alphanumeric sender ID (1–11 chars), pre-registered with Altiria.', 'wp-sms'),
                    ],
                    'unicode' => [
                        'type'        => 'boolean',
                        'label'       => __('Force Unicode', 'wp-sms'),
                        'default'     => false,
                        'description' => __('Enable for messages containing non-GSM-7 characters. Halves the per-segment limit.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('Altiria credentials are not configured.', 'wp-sms'));
        }

        $messagePayload = [
            'msg'    => $message->getBody(),
            'concat' => true,
        ];

        $from = (string) $this->getChannelConfig('sms', 'from', '');
        if ($from !== '') {
            $messagePayload['senderId'] = $from;
        }

        if ($this->getChannelConfig('sms', 'unicode')) {
            $messagePayload['encoding'] = 'unicode';
        }

        $body = [
            'credentials' => [
                'login'  => $username,
                'passwd' => $password,
            ],
            'destination' => [ltrim($message->getRecipient(), '+')],
            'message'     => $messagePayload,
            'source'      => self::SOURCE,
        ];

        $result = $this->httpPost(self::SEND_ENDPOINT, [
            'headers' => [
                'Content-Type' => 'application/json;charset=UTF-8',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $code = (int) $result['code'];
        if ($code < 200 || $code >= 300) {
            return DeliveryResult::failed(
                sprintf(__('Altiria HTTP error (%d).', 'wp-sms'), $code),
            );
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['status'])) {
            return DeliveryResult::failed(__('Altiria returned an unexpected response.', 'wp-sms'));
        }

        $status = (string) $data['status'];
        if ($status !== '000') {
            return DeliveryResult::failed(
                $this->describeStatus($status),
                ['altiria_status' => $status],
            );
        }

        return DeliveryResult::sent();
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return null;
        }

        $result = $this->httpPost(self::CREDIT_ENDPOINT, [
            'headers' => [
                'Content-Type' => 'application/json;charset=UTF-8',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode([
                'credentials' => ['login' => $username, 'passwd' => $password],
                'source'      => self::SOURCE,
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $code = (int) $result['code'];
        if ($code < 200 || $code >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || ($data['status'] ?? null) !== '000' || !isset($data['credit'])) {
            return null;
        }

        return (string) $data['credit'];
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('Username and Password are required', 'wp-sms'));
        }

        $result = $this->httpPost(self::CREDIT_ENDPOINT, [
            'headers' => [
                'Content-Type' => 'application/json;charset=UTF-8',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode([
                'credentials' => ['login' => $username, 'passwd' => $password],
                'source'      => self::SOURCE,
            ]),
        ]);

        $data = $this->validateTestResponse($result, 'Altiria');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $status = isset($data['status']) ? (string) $data['status'] : '';
        if ($status !== '000') {
            return TestConnectionResult::error($this->describeStatus($status));
        }

        $credit = isset($data['credit']) ? (string) $data['credit'] : 'N/A';
        return TestConnectionResult::ok(
            sprintf(__('Connected to Altiria. Balance: %s', 'wp-sms'), $credit),
            ['balance' => $credit],
        );
    }

    private function describeStatus(string $code): string
    {
        return match ($code) {
            '001'   => __('Altiria authentication missing or malformed.', 'wp-sms'),
            '002'   => __('Altiria service unavailable. Please retry later.', 'wp-sms'),
            '010'   => __('Invalid recipient number.', 'wp-sms'),
            '013'   => __('Message text is empty or too long.', 'wp-sms'),
            '014'   => __('Invalid sender ID. It must be 1–11 alphanumeric characters and pre-registered with Altiria.', 'wp-sms'),
            '015'   => __('Invalid encoding parameter.', 'wp-sms'),
            '016'   => __('Invalid certified-delivery parameter.', 'wp-sms'),
            '017'   => __('Invalid acknowledgement parameter.', 'wp-sms'),
            '018'   => __('Invalid acknowledgement identifier.', 'wp-sms'),
            '019'   => __('Invalid concatenation parameter.', 'wp-sms'),
            '020'   => __('Authentication failed. Check your Altiria credentials.', 'wp-sms'),
            '033'   => __('Insufficient Altiria credit.', 'wp-sms'),
            '034'   => __('Account inactive. Contact Altiria support.', 'wp-sms'),
            '035'   => __('Recipient number blocked.', 'wp-sms'),
            '036'   => __('Sender ID not authorized for this account.', 'wp-sms'),
            '037'   => __('Daily send quota exceeded.', 'wp-sms'),
            '038'   => __('Message rejected by Altiria filters.', 'wp-sms'),
            '039'   => __('Destination country not enabled for this account.', 'wp-sms'),
            ''      => __('Altiria returned an unexpected response.', 'wp-sms'),
            default => sprintf(__('Altiria returned status %s.', 'wp-sms'), $code),
        };
    }
}

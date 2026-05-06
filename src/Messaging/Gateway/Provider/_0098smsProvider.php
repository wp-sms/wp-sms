<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * 0098sms — Iranian SMS gateway exposing a SOAP service.
 *
 *   WSDL:    https://webservice.0098sms.com/service.asmx?wsdl
 *   Send:    SendSMS({ username, password, mobileno, pnlno, text, isflash })
 *            → returns a string. Numeric/string error codes signal failure;
 *              anything else is the provider message ID.
 *   Credit:  RemainSms({ username, password }) → numeric string credit, or
 *              an error code string on failure.
 *
 * Auth: panel username + password sent in every SOAP call.
 * Response shape: bare string (no envelope). Errors are well-known codes
 * (-3, 10, 11, -17, -18, -19, -22, 66, 1111, "Hang", "Doc N", "No Doc"),
 * everything else is treated as the message identifier.
 *
 * Out of scope (not exposed by the WSDL): MMS, status webhooks (poll-only),
 * inbound webhooks, opt-out detection. The gateway has no template / pattern
 * endpoint, so SupportsTemplates is intentionally not implemented.
 *
 * v7 → v8 reconciliation: v7 used PHP's SoapClient and called RemainSms
 * twice on every send (once as a credit pre-check, once as part of the send
 * call). v8 keeps the SoapClient transport but drops the redundant pre-check
 * — the gateway's own SendSMS error codes already surface insufficient credit
 * (-19). v7 returned Persian error strings; v8 returns provider-neutral
 * English messages keyed off the same numeric codes.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class _0098smsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const WSDL = 'https://webservice.0098sms.com/service.asmx?wsdl';

    /** @var array<string, string> Provider error code → user-facing message. */
    private const ERROR_MESSAGES = [
        '-3'     => 'Username and password do not match. Please contact support.',
        '10'     => 'Incorrect username or password. Please contact support.',
        '11'     => 'Message contains illegal characters.',
        '-17'    => 'Message text is empty.',
        '-18'    => 'Account charge error. Please contact support.',
        '-19'    => 'Insufficient credit. Please top up your panel.',
        '-22'    => 'Invalid mobile number.',
        '66'     => 'Username and password do not match. Please contact support.',
        '1111'   => 'Message contains illegal characters.',
        'Hang'   => 'Account is suspended. Please contact support.',
        'Doc N'  => 'Account registration is incomplete. Please complete registration.',
        'No Doc' => 'Account registration is incomplete. Please complete registration.',
    ];

    public function getId(): string
    {
        return '0098sms';
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
                    'description' => __('Your 0098sms panel username.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your 0098sms panel password.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated panel line purchased from 0098sms.', 'wp-sms'),
                        'placeholder' => '9810000000',
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
            return DeliveryResult::failed(__('0098sms credentials not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('0098sms sender not configured', 'wp-sms'));
        }

        if (!class_exists(\SoapClient::class)) {
            return DeliveryResult::failed(__('PHP SOAP extension is required for 0098sms.', 'wp-sms'));
        }

        $meta    = $message->getMeta();
        $isFlash = !empty($meta['flash']);

        try {
            $client = $this->createSoapClient();

            $result = $client->SendSMS([
                'username' => $username,
                'password' => $password,
                'mobileno' => $message->getRecipient(),
                'pnlno'    => $sender,
                'text'     => $message->getBody(),
                'isflash'  => $isFlash,
            ])->SendSMSResult ?? '';
        } catch (\SoapFault $e) {
            return DeliveryResult::failed($e->getMessage());
        } catch (\Throwable $e) {
            return DeliveryResult::failed($e->getMessage());
        }

        return $this->parseResponse((string) $result);
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        if ($username === '' || $password === '' || !class_exists(\SoapClient::class)) {
            return null;
        }

        try {
            $client = $this->createSoapClient();
            $result = (string) ($client->RemainSms([
                'username' => $username,
                'password' => $password,
            ])->RemainSmsResult ?? '');
        } catch (\Throwable $e) {
            return null;
        }

        if ($result === '' || isset(self::ERROR_MESSAGES[$result])) {
            return null;
        }

        return $result;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        if (!class_exists(\SoapClient::class)) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for 0098sms.', 'wp-sms'));
        }

        try {
            $client = $this->createSoapClient();
            $result = (string) ($client->RemainSms([
                'username' => $username,
                'password' => $password,
            ])->RemainSmsResult ?? '');
        } catch (\SoapFault $e) {
            return TestConnectionResult::error($e->getMessage());
        } catch (\Throwable $e) {
            return TestConnectionResult::error(
                sprintf(__('Could not reach the 0098sms API. Check your server\'s internet connection.', 'wp-sms'))
            );
        }

        if ($result === '') {
            return TestConnectionResult::error(__('Empty response from 0098sms', 'wp-sms'));
        }

        if (isset(self::ERROR_MESSAGES[$result])) {
            return TestConnectionResult::error(self::ERROR_MESSAGES[$result]);
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $result),
            ['credit' => $result],
        );
    }

    private function createSoapClient(): \SoapClient
    {
        return new \SoapClient(self::WSDL, ['encoding' => 'UTF-8']);
    }

    private function parseResponse(string $result): DeliveryResult
    {
        $result = trim($result);

        if ($result === '') {
            return DeliveryResult::failed(__('Empty response from 0098sms', 'wp-sms'));
        }

        if (isset(self::ERROR_MESSAGES[$result])) {
            return DeliveryResult::failed(
                self::ERROR_MESSAGES[$result],
                meta: ['0098sms_code' => $result],
            );
        }

        // Unknown non-success codes (negatives, short numerics) — treat as
        // failures with the bare code so admins can report them upstream.
        if (preg_match('/^-?\d+$/', $result) && (int) $result <= 0) {
            return DeliveryResult::failed(
                sprintf(__('0098sms rejected the message (code %s)', 'wp-sms'), $result),
                meta: ['0098sms_code' => $result],
            );
        }

        return DeliveryResult::sent($result);
    }
}

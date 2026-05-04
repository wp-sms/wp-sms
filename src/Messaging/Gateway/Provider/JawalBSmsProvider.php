<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Jawal B SMS — Saudi-Arabia-based SMS aggregator (جوال بي اس ام اس)
 * with reach across the GCC, Egypt, and Turkey.
 *
 * API contract (verbatim from the v7 wp-sms gateway; jawalbsms.ws does not
 * publish a public endpoint reference):
 *   Send:    GET https://www.jawalbsms.ws/api.php/sendsms
 *            ?user=…&pass=…&to=…&message=…&sender=…
 *   Balance: GET https://www.jawalbsms.ws/api.php/chk_balance
 *            ?user=…&pass=…
 *   Response: plain text. Success bodies contain the literal substring
 *             "Success"; failure bodies are negative integer codes
 *             (-100, -110, -111, -112, -114, -116, -120, -130).
 *
 * Out of scope (provider does not document any of these): DLR webhook,
 * inbound MO, templates, opt-out detection, dynamic sender lookup, MMS,
 * flash SMS, or any non-SMS channel.
 */
final class JawalBSmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://www.jawalbsms.ws/api.php';

    public function getId(): string
    {
        return 'jawalbsms';
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
                    'description' => __('Your Jawal B SMS account username (the same one you use to log in at jawalbsms.ws).', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Jawal B SMS account password.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'COMPANY',
                        'description' => __('Pre-approved alphanumeric sender ID. For Saudi recipients this must be registered with the Saudi numbering authority through your Jawal B SMS account.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $from     = (string) $this->getChannelConfig('sms', 'from', '');

        if ($username === '' || $password === '' || $from === '') {
            return DeliveryResult::failed(__('Jawal B SMS credentials not configured', 'wp-sms'));
        }

        $url = self::API_BASE . '/sendsms?' . http_build_query([
            'user'    => $username,
            'pass'    => $password,
            'to'      => ltrim($message->getRecipient(), '+'),
            'message' => $message->getBody(),
            'sender'  => $from,
        ]);

        $result = $this->httpGet($url);
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $body = $this->stripNonPrintable((string) $result['body']);

        if (stripos($body, 'Success') !== false) {
            return DeliveryResult::sent(providerId: $body);
        }

        return DeliveryResult::failed($this->mapErrorCode($body));
    }

    public function getCredit(): ?string
    {
        $result = $this->fetchBalance();
        if ($result === null) {
            return null;
        }

        $balance = (int) $result;
        return $balance > 0 ? (string) $balance : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('Jawal B SMS credentials not configured', 'wp-sms'));
        }

        $body = $this->fetchBalance();
        if ($body === null) {
            return TestConnectionResult::error(
                __('Could not reach the Jawal B SMS API. Check your server\'s internet connection.', 'wp-sms')
            );
        }

        $balance = (int) $body;
        if ($balance > 0) {
            return TestConnectionResult::ok(
                sprintf(__('Connected. Balance: %d', 'wp-sms'), $balance)
            );
        }

        return TestConnectionResult::error($this->mapErrorCode($body));
    }

    /**
     * GET chk_balance and return the cleaned response body, or null on
     * a network failure or unconfigured credentials.
     */
    private function fetchBalance(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return null;
        }

        $url = self::API_BASE . '/chk_balance?' . http_build_query([
            'user' => $username,
            'pass' => $password,
        ]);

        $result = $this->httpGet($url);
        if ($result instanceof DeliveryResult) {
            return null;
        }

        return $this->stripNonPrintable((string) $result['body']);
    }

    /**
     * Strip non-printable characters (e.g. BOM-like bytes the server
     * occasionally injects) so the body matches the literal codes below.
     */
    private function stripNonPrintable(string $body): string
    {
        return trim((string) preg_replace('/[[:^print:]]/', '', $body));
    }

    /**
     * Translate the provider's negative integer error codes into the
     * human-readable strings carried over verbatim from v7. Unknown
     * codes are echoed back unchanged.
     */
    private function mapErrorCode(string $body): string
    {
        $errors = [
            '-100' => __('Missing parameters (not exist or empty) Username +password', 'wp-sms'),
            '-110' => __('Account not exist (wrong username or password)', 'wp-sms'),
            '-111' => __('The account not activated', 'wp-sms'),
            '-112' => __('Blocked account', 'wp-sms'),
            '-114' => __('The service not available for now', 'wp-sms'),
            '-116' => __('Invalid sender name', 'wp-sms'),
            '-120' => __('No destination addresses, or all destinations are incorrect', 'wp-sms'),
            '-130' => __('Error in MsgID (used with cancel schedule message)', 'wp-sms'),
        ];

        return $errors[$body] ?? $body;
    }
}

<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * SL Interactive (smsmessenger.com.au) — Australian bulk-SMS provider operated
 * by SL Interactive Pty Ltd (Melbourne, est. 2005).
 *
 * Auth: case-sensitive `uname` + `pword` query parameters on every request.
 * Send: GET /api/send_sms.php with `uname`, `pword`, `msg`, `to` (digits-only,
 * country-code-prefixed) and an optional `sid` sender alias. The endpoint
 * accepts comma-separated `to` values, but WSMS dispatches per-recipient so
 * we always send a single number.
 *
 * Responses are plain text. `Complete:N` is success (N = number of recipients);
 * everything else is an error token (`I_UNAME_PWORD`, `CREDIT:X`, `PHONE_NO`,
 * `M_LENGTH`, `NO_USER`, `NO_PWORD`, `NO_MSG`, `NO_TO`).
 *
 * Out of scope (provider does not document any of these): inbound MO, DLR
 * webhook, opt-out detection, dynamic sender lookup, templates, regulatory IDs.
 * Production sends never return an order ID — only the demo endpoint does — so
 * we persist null for providerId rather than fabricate one.
 *
 * The HTTP API doc spells `http://`, but the host serves both schemes; we
 * always use HTTPS so credentials are never transmitted in plaintext.
 */
class SlinteractiveProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_ENDPOINT = 'https://www.slinteractive.com.au/api/send_sms.php';

    public function getId(): string
    {
        return 'slinteractive';
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
                    'description' => __('Your SL Interactive username (case-sensitive). The email/login from your smsmessenger.com.au account.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your SL Interactive password (case-sensitive).', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => false,
                        'placeholder' => 'MyBrand',
                        'description' => __('Optional alphanumeric Sender ID (max 11 chars). Leave blank to use the SL Interactive default originator (e.g. 0447100265). Custom Sender IDs require approval from SL Interactive support.', 'wp-sms'),
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
            return DeliveryResult::failed(__('SL Interactive credentials not configured', 'wp-sms'));
        }

        $params = [
            'uname' => $username,
            'pword' => $password,
            'msg'   => $message->getBody(),
            'to'    => $this->normalizeRecipient($message->getRecipient()),
        ];

        $senderId = (string) $this->getChannelConfig('sms', 'sender_id', '');
        if ($senderId !== '') {
            $params['sid'] = $senderId;
        }

        $result = $this->httpGet(add_query_arg($params, self::SEND_ENDPOINT));
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $code = (int) $result['code'];
        if ($code < 200 || $code >= 300) {
            return DeliveryResult::failed(sprintf(__('SL Interactive: HTTP %d', 'wp-sms'), $code));
        }

        return $this->parseSendResponse(trim((string) $result['body']));
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('SL Interactive username and password are required', 'wp-sms'));
        }

        // Trick: submitting credentials without a `msg` returns `NO_MSG` for
        // valid creds and `I_UNAME_PWORD` for bad ones — no SMS is sent and
        // no credit is consumed.
        $url = add_query_arg([
            'uname' => $username,
            'pword' => $password,
        ], self::SEND_ENDPOINT);

        $result = $this->httpGet($url);
        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the SL Interactive API. Check your server\'s internet connection.', 'wp-sms'),
            );
        }

        $code = (int) $result['code'];
        if ($code < 200 || $code >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from SL Interactive (HTTP %d)', 'wp-sms'), $code),
            );
        }

        $body = trim((string) $result['body']);

        if ($body === 'NO_MSG') {
            return TestConnectionResult::ok(__('Connected to SL Interactive', 'wp-sms'));
        }

        if ($body === 'I_UNAME_PWORD' || $body === 'NO_USER' || $body === 'NO_PWORD') {
            return TestConnectionResult::error(__('Invalid SL Interactive credentials', 'wp-sms'));
        }

        return TestConnectionResult::error(
            $body !== ''
                ? sprintf(__('SL Interactive: %s', 'wp-sms'), $body)
                : __('SL Interactive returned an empty response', 'wp-sms'),
        );
    }

    private function parseSendResponse(string $body): DeliveryResult
    {
        if ($body === '') {
            return DeliveryResult::failed(__('SL Interactive returned an empty response', 'wp-sms'));
        }

        if (str_starts_with($body, 'Complete:')) {
            // Production responses do not include an order ID, only the demo
            // endpoint does; persist null rather than fabricate one.
            return DeliveryResult::sent();
        }

        if ($body === 'I_UNAME_PWORD') {
            return DeliveryResult::failed(__('Invalid SL Interactive username/password', 'wp-sms'));
        }

        if (str_starts_with($body, 'CREDIT:')) {
            $remaining = substr($body, strlen('CREDIT:'));
            return DeliveryResult::failed(
                sprintf(__('Out of SL Interactive credit (remaining: %s)', 'wp-sms'), $remaining),
                ['sli_credit_remaining' => $remaining],
            );
        }

        if ($body === 'PHONE_NO' || str_starts_with($body, 'PHONE_NO:')) {
            return DeliveryResult::failed(__('SL Interactive: invalid recipient phone number', 'wp-sms'));
        }

        if ($body === 'M_LENGTH') {
            return DeliveryResult::failed(__('Message exceeds 160 characters; SL Interactive rejected concatenation', 'wp-sms'));
        }

        if (in_array($body, ['NO_USER', 'NO_PWORD', 'NO_MSG', 'NO_TO'], true)) {
            return DeliveryResult::failed(sprintf(__('SL Interactive: %s', 'wp-sms'), $body));
        }

        return DeliveryResult::failed(sprintf(__('SL Interactive: %s', 'wp-sms'), $body));
    }

    private function normalizeRecipient(string $recipient): string
    {
        return (string) preg_replace('/\D+/', '', $recipient);
    }
}

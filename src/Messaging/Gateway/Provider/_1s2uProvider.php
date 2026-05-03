<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

/**
 * 1s2u — global SMS aggregator. SMS only.
 *
 * Auth: form-encoded username/password on every request (no API key tokens).
 * Send: POST /bulksms — text body, "OK:<id>" on success, bare error code on failure.
 * Balance: GET /checkbalance — plain-text balance, "00" if creds are bad.
 * DLR: 1s2u posts unsigned form-data to a configured webhook URL. We protect
 *      the endpoint with a per-install random token appended as ?token=… on
 *      the URL the admin pastes into the 1s2u portal.
 *
 * v7 → v8 reconciliation: v7 sent `Sid` (PascalCase), `USER`/`PASS` (uppercase),
 * and prefixed `00` to MSISDNs. Public docs are explicit on `sid`, `user`, `pass`,
 * and digits-only MSISDNs; v8 follows the docs.
 */
// TODO(inbound): 1s2u offers MO inbound via virtual numbers, but the field schema
// isn't published. Defer SupportsInboundMessage until portal verification.
// TODO(verify): 1s2u has an OTP product resembling Twilio Verify; defer until
// the SupportsVerify cross-cutting interface lands.
class _1s2uProvider extends AbstractProvider implements SupportsStatusCallback
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = true;

    private const SEND_URL    = 'https://api.1s2u.io/bulksms';
    private const BALANCE_URL = 'https://api.1s2u.io/checkbalance';

    /** @var array<string, string> Error code → user-facing message. Verbatim port from v7. */
    private const ERROR_MESSAGES = [
        '0000' => 'Service Not Available or Down Temporary',
        '00'   => 'Invalid username/password.',
        '0005' => 'Invalid server',
        '0010' => 'Username not provided.',
        '0011' => 'Password not provided.',
        '0'    => 'Insufficient Credits',
        '0020' => 'Insufficient Credits',
        '0030' => 'Invalid Sender ID',
        '0040' => 'Mobile number not provided.',
        '0041' => 'Invalid mobile number',
        '0042' => 'Network not supported.',
        '0050' => 'Invalid message.',
        '0060' => 'Invalid quantity specified.',
        '0066' => 'Network not supported',
    ];

    /** Codes that won't succeed on retry without admin action. */
    private const PERMANENT_ERROR_CODES = ['0030', '0041', '0042', '0066'];

    public function getId(): string
    {
        return '1s2u';
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
                    'description' => __('Your 1s2u account username.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your 1s2u account password.', 'wp-sms'),
                ],
                'webhook_token' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Auto-generated random token used to authenticate DLR callbacks. Append as ?token=… on the callback URL configured in the 1s2u portal.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Sender ID — max 11 alphanumeric or 15 numeric characters.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');
        $sender   = $this->getChannelConfig('sms', 'from');

        if (!$username || !$password) {
            return DeliveryResult::failed(__('1s2u credentials not configured', 'wp-sms'));
        }
        if (!$sender) {
            return DeliveryResult::failed(__('1s2u sender ID not configured', 'wp-sms'));
        }

        $body = $message->getBody();
        $meta = $message->getMeta();

        $params = [
            'username' => $username,
            'password' => $password,
            'mno'      => $this->normalizeNumber($message->getRecipient()),
            'sid'      => $sender,
            'msg'      => rawurlencode($body),
            'mt'       => $this->isUnicode($body) ? 1 : 0,
            'fl'       => !empty($meta['flash']) ? 1 : 0,
        ];

        $result = $this->httpPost(self::SEND_URL, ['body' => $params]);
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        return $this->parseSendResponse($result['body'], $result['code']);
    }

    public function getCredit(): ?string
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');
        if (!$username || !$password) {
            return null;
        }

        $result = $this->httpGet(add_query_arg(
            ['user' => $username, 'pass' => $password],
            self::BALANCE_URL,
        ));

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $balance = trim($result['body']);
        if ($balance === '' || isset(self::ERROR_MESSAGES[$balance])) {
            return null;
        }

        return $balance;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');
        if (!$username || !$password) {
            return TestConnectionResult::error(__('Username and password are required', 'wp-sms'));
        }

        $result = $this->httpGet(add_query_arg(
            ['user' => $username, 'pass' => $password],
            self::BALANCE_URL,
        ));

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the 1s2u API. Check your server\'s internet connection.', 'wp-sms')
            );
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from 1s2u (HTTP %d)', 'wp-sms'), $result['code'])
            );
        }

        $balance = trim($result['body']);
        if ($balance === '00') {
            return TestConnectionResult::error(__('Invalid 1s2u username or password', 'wp-sms'));
        }
        if ($balance === '' || isset(self::ERROR_MESSAGES[$balance])) {
            return TestConnectionResult::error(
                self::ERROR_MESSAGES[$balance] ?? __('1s2u rejected the request', 'wp-sms')
            );
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s', 'wp-sms'), $balance),
            ['balance' => $balance],
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        $token = $this->getSharedConfig('webhook_token');
        $args = $token ? ['token' => $token] : [];
        return RestRoute::url('callbacks/' . $this->getId() . '/status', $args);
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        $expected = (string) $this->getSharedConfig('webhook_token', '');
        if ($expected === '') {
            // 1s2u does not sign callbacks. Without a token configured the
            // endpoint is unauthenticated; refuse to process rather than trust
            // arbitrary inbound requests.
            return false;
        }

        $provided = (string) ($request->get_param('token') ?? '');
        if ($provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $providerId = (string) ($request->get_param('sms_id') ?? '');
        $rawStatus = strtoupper((string) ($request->get_param('response') ?? ''));

        if ($providerId === '' || $rawStatus === '') {
            return [];
        }

        $status = match ($rawStatus) {
            'DELIVRD'           => 'delivered',
            'UNDELIV', 'EXPIRED' => 'failed',
            default             => strtolower($rawStatus),
        };

        return [new StatusUpdate(
            providerId:   $providerId,
            status:       $status,
            errorCode:    $status === 'failed' ? $rawStatus : null,
            errorMessage: $status === 'failed' ? sprintf('1s2u: %s', $rawStatus) : null,
            permanent:    $status === 'failed',
        )];
    }

    // --- Internal ---

    private function parseSendResponse(string $body, int $statusCode): DeliveryResult
    {
        $body = trim($body);

        if ($body !== '' && stripos($body, 'OK') === 0) {
            // "OK:<id>" or "OK: <id>" or "OK<id>"
            $id = trim(substr($body, 2), ": \t\n\r\0");
            return DeliveryResult::queued($id !== '' ? $id : null);
        }

        // Strip "ERROR:" prefix if present, otherwise treat the whole body as the code.
        $code = $body;
        if (preg_match('/^ERROR[:\s]\s*(\S+)/i', $body, $matches)) {
            $code = $matches[1];
        }

        if ($code === '' && $statusCode >= 400) {
            return DeliveryResult::failed(sprintf('HTTP %d', $statusCode));
        }

        $message = self::ERROR_MESSAGES[$code]
            ?? __("Something's wrong. Please contact the SMS gateway provider support team.", 'wp-sms');

        $permanent = in_array($code, self::PERMANENT_ERROR_CODES, true);

        return DeliveryResult::failed(
            $message,
            meta: ['1s2u_code' => $code],
            retryable: !$permanent && $code === '0000',
        );
    }

    private function normalizeNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number);
        if ($digits === null) {
            return '';
        }
        // Strip a leading 00 international prefix if present (some admins paste
        // numbers in 00<cc>… form even though docs ask for digits only with the
        // country code first).
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        return $digits;
    }

    private function isUnicode(string $body): bool
    {
        if ($body === '') {
            return false;
        }
        if (!mb_check_encoding($body, 'ASCII')) {
            return true;
        }
        // GSM-7 is broader than ASCII (covers €, £, etc.) but ASCII is a safe
        // subset — anything beyond ASCII gets the unicode flag.
        return false;
    }
}

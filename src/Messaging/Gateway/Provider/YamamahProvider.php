<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Yamamah — Saudi Arabian SMS gateway operated by 2P (api.yamamah.com).
 *
 * REST/JSON API (HTTP, not HTTPS per the published spec):
 *   Send:    POST /SendSMS  with JSON {Username, Password, Tagname,
 *            RecepientNumber, VariableList, ReplacementList, Message,
 *            SendDateTime, EnableDR}
 *   Credit:  POST /GetCredit/{Username}/{Password}  (empty body)
 *
 * Auth: account username (or registered 9665… mobile) + password sent in
 * every request body or path.
 *
 * Out of scope on first port: status callbacks (the /MessageNotification
 * webhook is set up via app.support@2p.com.sa email — not self-service),
 * inbound, opt-out, dynamic options, templates.
 *
 * Coverage is KSA-only per the provider's own positioning; international
 * numbers are nominally accepted with a 00 prefix but that's routing, not
 * documented destination scope.
 *
 * Reference: 2P "Yamamah Integration Document" (Feb 2015 spec). The live
 * API is unreachable from US IPs; ships with TESTED = false until manually
 * verified against a Saudi-side line.
 */
// TODO(status-callback): /MessageNotification webhook is set up via
// app.support@2p.com.sa email; auth is username/password in body.
// Implement SupportsStatusCallback when a self-service path exists.
class YamamahProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'http://api.yamamah.com';

    public function getId(): string
    {
        return 'yamamah';
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
                    'placeholder' => '9665xxxxxxx or your Yamamah username',
                    'description' => __('Your Yamamah account username, or the 9665… mobile number registered on the Yamamah portal.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Yamamah account password.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender (TagName)', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Approved TagName from your Yamamah account, max 11 chars English/digits. Defaults to your registered mobile number on the Yamamah portal if you leave this set to that number.', 'wp-sms'),
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
            return DeliveryResult::failed(__('Yamamah credentials not configured', 'wp-sms'));
        }

        if ($sender === '') {
            return DeliveryResult::failed(__('Yamamah sender (TagName) not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/SendSMS', [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode([
                'Username'        => $username,
                'Password'        => $password,
                'Tagname'         => $sender,
                'RecepientNumber' => $message->getRecipient(),
                'VariableList'    => '',
                'ReplacementList' => '',
                'Message'         => $message->getBody(),
                'SendDateTime'    => 0,
                'EnableDR'        => false,
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(__('Invalid response from Yamamah', 'wp-sms'));
        }

        $status = (int) ($data['Status'] ?? 0);
        if ($status === 1) {
            return DeliveryResult::sent((string) ($data['MessageID'] ?? ''));
        }

        $error = $this->mapStatusError($status)
            ?? (string) ($data['StatusDescription'] ?? __('Yamamah send failed', 'wp-sms'));

        return DeliveryResult::failed($error);
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        if ($username === '' || $password === '') {
            return null;
        }

        $result = $this->httpPost($this->buildCreditUrl($username, $password));

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return null;
        }

        $inner = $data['GetCreditPostResult'] ?? null;
        if (!is_array($inner) || (int) ($inner['Status'] ?? 0) !== 1) {
            return null;
        }

        return (string) ($inner['Credit'] ?? '');
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        $result = $this->httpPost($this->buildCreditUrl($username, $password));

        $data = $this->validateTestResponse($result, 'Yamamah');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $inner = $data['GetCreditPostResult'] ?? null;
        if (!is_array($inner)) {
            return TestConnectionResult::error(__('Invalid response from Yamamah', 'wp-sms'));
        }

        $status = (int) ($inner['Status'] ?? 0);
        if ($status !== 1) {
            $message = $this->mapStatusError($status)
                ?? (string) ($inner['Description'] ?? __('Unknown error', 'wp-sms'));
            return TestConnectionResult::error($message);
        }

        $credit = (string) ($inner['Credit'] ?? 'N/A');
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }

    private function buildCreditUrl(string $username, string $password): string
    {
        return self::API_BASE . '/GetCredit/' . rawurlencode($username) . '/' . rawurlencode($password);
    }

    private function mapStatusError(int $status): ?string
    {
        $map = [
            10 => __('Invalid credentials (username or password)', 'wp-sms'),
            20 => __('Invalid TagName format', 'wp-sms'),
            30 => __('Invalid TagName — not approved on this account', 'wp-sms'),
            40 => __('Insufficient credit', 'wp-sms'),
            50 => __('Invalid recipient or replacement list', 'wp-sms'),
            51 => __('Invalid VariableList or ReplacementList', 'wp-sms'),
            60 => __('Invalid mobile number', 'wp-sms'),
            70 => __('Yamamah system error — try again later', 'wp-sms'),
            80 => __('Invalid scheduled date/time', 'wp-sms'),
            90 => __('Yamamah serialization error', 'wp-sms'),
        ];

        return $map[$status] ?? null;
    }
}

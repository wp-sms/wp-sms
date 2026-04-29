<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Msegat — Saudi-headquartered SMS gateway focused on KSA / GCC.
 *
 * Send: POST https://www.msegat.com/gw/sendsms.php
 *   Headers: Content-Type: application/json
 *   Body (JSON): {userName, apiKey, userSender, numbers, msg, msgEncoding}
 *   Numbers must be international without leading + or 00 (e.g. 9665XXXXXXXX).
 *   Response (JSON): {code, message, id?} — `code === 1` is success.
 *
 * Auth: API key (per Apiary docs and v7 legacy class). Username + apiKey in
 *   the JSON body — never the user's account password.
 *
 * Credit / test connection: POST https://www.msegat.com/gw/Credits.php
 *   Body (form): userName, apiKey, msgEncoding=UTF8
 *   Response: numeric balance string on success, or M0xxx / 1xxx error code.
 *
 * Sender list (dynamic options): GET https://www.msegat.com/gw/getSenders.php
 *   Query: userName, apiKey, msgEncoding=UTF8, type=All
 *   Response (JSON): {code, senders:[{SenderID, Status}, ...]} — only
 *   "Activated" senders are eligible for use.
 *
 * Deferred capabilities (intentionally not implemented):
 *
 * @todo callback — Msegat exposes DLR + inbound MO webhooks but the payload
 *   shape and signature scheme are not documented in the SDK or Apiary;
 *   defer SupportsStatusCallback / SupportsInboundMessage until the spec
 *   is captured from a live send.
 *
 * @todo verify — Free OTP traffic is delivered via /sendsms.php with a
 *   constrained body, not via a /verify/start + /verify/check lifecycle,
 *   so SupportsVerify (when WSMS adds it) is not relevant here.
 *
 * @todo whatsapp — Msegat advertises a WhatsApp Business product but it is
 *   dashboard-driven and behind WABA + paid plan; no public API endpoint
 *   is documented. Add a 'whatsapp' channel only if the provider publishes
 *   a REST surface.
 */
class MsegatProvider extends AbstractProvider implements SupportsDynamicOptions
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://www.msegat.com/gw';

    public function getId(): string
    {
        return 'msegat';
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
                    'placeholder' => 'mycompany',
                    'description' => __('Your Msegat account username (not the login email).', 'wp-sms'),
                ],
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generate this under Account → API Keys in the Msegat dashboard.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_name' => [
                        'type'        => 'select',
                        'label'       => __('Sender Name', 'wp-sms'),
                        'required'    => true,
                        'dynamic'     => true,
                        'placeholder' => __('Select an approved sender', 'wp-sms'),
                        'description' => __('Pre-approved CST sender name. Promotional senders must end with "-AD".', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = $this->getSharedConfig('username');
        $apiKey   = $this->getSharedConfig('api_key');
        $sender   = $this->getChannelConfig('sms', 'sender_name');

        if (!$username || !$apiKey) {
            return DeliveryResult::failed(__('Msegat credentials not configured', 'wp-sms'));
        }
        if (!$sender) {
            return DeliveryResult::failed(__('Msegat sender name not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/sendsms.php', [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'userName'    => $username,
                'apiKey'      => $apiKey,
                'userSender'  => $sender,
                'numbers'     => $message->getRecipient(),
                'msg'         => $message->getBody(),
                'msgEncoding' => 'UTF8',
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        $code = $data['code'] ?? null;

        if ($code === 1 || $code === '1' || $code === 'M0000') {
            return DeliveryResult::sent(
                providerId: isset($data['id']) ? (string) $data['id'] : null,
            );
        }

        $codeStr = $code !== null ? (string) $code : trim((string) $result['body']);
        $error = $this->describeError($codeStr) ?? ($data['message'] ?? __('Msegat send failed', 'wp-sms'));

        return DeliveryResult::failed($error, meta: ['msegat_code' => $codeStr]);
    }

    public function getCredit(): ?string
    {
        $username = $this->getSharedConfig('username');
        $apiKey   = $this->getSharedConfig('api_key');

        if (!$username || !$apiKey) {
            return null;
        }

        $result = $this->httpPost(self::API_BASE . '/Credits.php', [
            'body' => [
                'userName'    => $username,
                'apiKey'      => $apiKey,
                'msgEncoding' => 'UTF8',
            ],
        ]);

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
        $apiKey   = $this->getSharedConfig('api_key');

        if (!$username || !$apiKey) {
            return TestConnectionResult::error(__('API Username and API Key are required', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/Credits.php', [
            'body' => [
                'userName'    => $username,
                'apiKey'      => $apiKey,
                'msgEncoding' => 'UTF8',
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the Msegat API. Check your server\'s internet connection.', 'wp-sms')
            );
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from Msegat (HTTP %d)', 'wp-sms'), $result['code'])
            );
        }

        $body = trim((string) $result['body']);
        $error = $this->describeError($body);
        if ($error !== null) {
            return TestConnectionResult::error($error);
        }

        if ($body === '') {
            return TestConnectionResult::error(__('Invalid response from Msegat', 'wp-sms'));
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $body),
            ['credit' => $body],
        );
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'sender_name' || $section !== 'sms') {
            return [];
        }

        return $this->withConfig($config, function () {
            $username = $this->getSharedConfig('username');
            $apiKey   = $this->getSharedConfig('api_key');

            if (!$username || !$apiKey) {
                throw new \RuntimeException(__('Enter API Username and API Key first', 'wp-sms'));
            }

            $url = self::API_BASE . '/getSenders.php?' . http_build_query([
                'userName'    => $username,
                'apiKey'      => $apiKey,
                'msgEncoding' => 'UTF8',
                'type'        => 'All',
            ]);

            $data = $this->fetchJsonOrFail($url);

            $code = $data['code'] ?? null;
            if ($code !== null && $code !== 1 && $code !== '1' && $code !== 'M0000') {
                $error = $this->describeError((string) $code) ?? ($data['message'] ?? __('Could not fetch senders', 'wp-sms'));
                throw new \RuntimeException($error);
            }

            $rows = $data['senders'] ?? $data['data'] ?? [];
            $options = [];

            foreach ($rows as $row) {
                $senderId = $row['SenderID'] ?? $row['senderID'] ?? $row['sender'] ?? '';
                $status   = $row['Status'] ?? $row['status'] ?? '';

                if ($senderId === '' || strcasecmp($status, 'Activated') !== 0) {
                    continue;
                }

                $options[] = ['value' => (string) $senderId, 'label' => (string) $senderId];
            }

            return $options;
        });
    }

    /**
     * Map a Msegat error/status code to a localised, user-facing message.
     * Returns null when the code is unknown or success ("1"/"M0000").
     * Codes ported from v7 (`includes/gateways/class-wpsms-gateway-msegat.php`)
     * and confirmed against the current Apiary docs.
     */
    private function describeError(string $code): ?string
    {
        switch ($code) {
            case '1':
            case 'M0000':
                return null;
            case 'M0001':
            case '1010':
                return __('Variables missing.', 'wp-sms');
            case 'M0002':
            case '1020':
                return __('Invalid login info.', 'wp-sms');
            case 'M0022':
                return __('Exceeded the number of senders allowed.', 'wp-sms');
            case 'M0023':
                return __('Sender name is active, under activation, or refused.', 'wp-sms');
            case 'M0024':
                return __('Sender name should be in English or numeric only.', 'wp-sms');
            case 'M0025':
                return __('Invalid sender name length.', 'wp-sms');
            case 'M0026':
                return __('Sender name is already activated or not found.', 'wp-sms');
            case 'M0027':
                return __('Activation code is incorrect.', 'wp-sms');
            case 'M0029':
                return __('Invalid sender name — letters and numbers only, max 11 characters.', 'wp-sms');
            case 'M0030':
                return __('Promotional sender name must end with "-AD".', 'wp-sms');
            case 'M0031':
                return __('Uploaded file must be 5 MB or smaller.', 'wp-sms');
            case 'M0032':
                return __('Only PDF, PNG, JPG, and JPEG files are allowed.', 'wp-sms');
            case 'M0033':
                return __('Sender type must be "normal" or "whitelist".', 'wp-sms');
            case 'M0034':
                return __('Please use the POST method.', 'wp-sms');
            case 'M0036':
                return __('No sender available on this account.', 'wp-sms');
            case '1050':
                return __('Message body is empty.', 'wp-sms');
            case '1060':
                return __('Account balance is not enough.', 'wp-sms');
            case '1061':
                return __('Message is duplicated.', 'wp-sms');
            case '1064':
                return __('Free OTP accounts only allow "Pin Code is: xxxx" or "Verification Code: xxxx" message bodies — upgrade the account to send custom content.', 'wp-sms');
            case '1110':
                return __('Sender name is missing or incorrect.', 'wp-sms');
            case '1120':
                return __('Mobile number is incorrect.', 'wp-sms');
            case '1140':
                return __('Message length is too long.', 'wp-sms');
            default:
                return null;
        }
    }
}

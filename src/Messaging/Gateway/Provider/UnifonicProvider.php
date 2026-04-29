<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Unifonic — Saudi-headquartered MENA messaging platform.
 *
 * Send: POST https://el.cloud.unifonic.com/rest/SMS/messages
 *   Body fields (form-encoded): AppSid, SenderID, Recipient, Body, responseType=JSON
 * Auth: HTTP Basic (account email + password) AND AppSid in body. The canonical
 *   APIMATIC SDK (sitmena/unifonic-integration, AbdelrahmanHafez/unifonic-next-gen-lib)
 *   declares both as required Configuration fields, and the v7 legacy class ships
 *   the same dual-auth shape against the same paths.
 *
 * Recipient must be in international format without leading + or 00 (per the
 * canonical SDK's docstring).
 *
 * Response shape:
 *   {"success":"true","message":"...","data":{"MessageID":"...","SenderID":"...",
 *    "Recipient":"...","Status":"..."}}
 *   `success` is a STRING ("true"/"false"), not a boolean — same quirk v7 handles.
 *
 * Verified error codes (from APIMATIC python/node SDK):
 *   401 = auth failed, 402 = missing AppSid, 449/480/482 = sender/recipient/validation
 *
 * Deferred capabilities (intentionally not implemented in v8):
 *
 * @todo voice — Unifonic exposes a Voice product (their .NET SDK is named
 *   "SMS-Voice"); defer until WSMS adds a 'voice' channel.
 *
 * @todo verify — Authenticate API at
 *   https://authenticate.cloud.api.unifonic.com/services/api/v2/verifications/{start,check}
 *   is a Verify-as-a-Service offering; defer until SupportsVerify lands.
 *
 * @todo callback — Unifonic supports a per-message statusCallback URL but the DLR
 *   payload shape is not exposed by any server-side SDK we verified; defer
 *   SupportsStatusCallback until captured from a live send.
 */
class UnifonicProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://el.cloud.unifonic.com';
    private const SEND_PATH = '/rest/SMS/messages';

    public function getId(): string
    {
        return 'unifonic';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'account_email' => [
                    'type'        => 'string',
                    'label'       => __('API Email', 'wp-sms'),
                    'required'    => true,
                    'placeholder' => 'you@example.com',
                    'description' => __('Email address you sign in to cloud.unifonic.com with — used as the HTTP Basic username.', 'wp-sms'),
                ],
                'account_password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Password for the same Unifonic account — used as the HTTP Basic password.', 'wp-sms'),
                ],
                'app_sid' => [
                    'type'        => 'secret',
                    'label'       => __('App SID', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Application identifier from cloud.unifonic.com → Dev Tools → REST.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'MyCompany',
                        'description' => __('Approved alphanumeric sender or registered phone number. SA traffic requires CITC-approved senders.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $email = (string) $this->getSharedConfig('account_email');
        $password = (string) $this->getSharedConfig('account_password');
        $appSid = (string) $this->getSharedConfig('app_sid');
        $sender = (string) $this->getChannelConfig('sms', 'sender_id');

        if ($email === '' || $password === '' || $appSid === '' || $sender === '') {
            return DeliveryResult::failed(__('Unifonic credentials not configured', 'wp-sms'));
        }

        $body = [
            'AppSid'       => $appSid,
            'SenderID'     => $sender,
            'Recipient'    => ltrim($message->getRecipient(), '+'),
            'Body'         => $message->getBody(),
            'responseType' => 'JSON',
        ];

        $result = $this->httpPost(self::API_BASE . self::SEND_PATH, [
            'headers' => $this->authHeaders($email, $password),
            'body'    => http_build_query($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401) {
            return DeliveryResult::failed(__('Invalid Unifonic credentials', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        // Unifonic returns `success` as a string "true"/"false", not a boolean.
        $isSuccess = is_array($data)
            && isset($data['success'])
            && in_array($data['success'], ['true', true], true);

        if ($result['code'] >= 200 && $result['code'] < 300 && $isSuccess) {
            $messageId = $data['data']['MessageID'] ?? null;
            return DeliveryResult::queued($messageId !== null ? (string) $messageId : null);
        }

        $errorMsg = is_array($data)
            ? ($data['message'] ?? sprintf(__('Unifonic send failed (HTTP %d)', 'wp-sms'), $result['code']))
            : sprintf(__('Unifonic send failed (HTTP %d)', 'wp-sms'), $result['code']);

        $errorCode = is_array($data) ? ($data['errorCode'] ?? null) : null;

        return DeliveryResult::failed(
            (string) $errorMsg,
            meta: array_filter([
                'unifonic_http' => $result['code'] ?: null,
                'unifonic_code' => $errorCode !== null ? (string) $errorCode : null,
            ]),
        );
    }

    public function testConnection(): TestConnectionResult
    {
        $email = (string) $this->getSharedConfig('account_email');
        $password = (string) $this->getSharedConfig('account_password');
        $appSid = (string) $this->getSharedConfig('app_sid');

        if ($email === '' || $password === '' || $appSid === '') {
            return TestConnectionResult::error(__('Email, password, and App SID are required', 'wp-sms'));
        }

        // No documented Balance endpoint, so probe the send endpoint with a body
        // that's authenticated but recipient-less. Unifonic returns 401 for bad
        // creds and a validation error (449/480/482) when auth is fine but
        // Recipient is missing — distinguishing the two confirms the credentials.
        $result = $this->httpPost(self::API_BASE . self::SEND_PATH, [
            'headers' => $this->authHeaders($email, $password),
            'body'    => http_build_query([
                'AppSid'       => $appSid,
                'responseType' => 'JSON',
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(__('Could not reach the Unifonic API. Check your server\'s internet connection.', 'wp-sms'));
        }

        if ($result['code'] === 401) {
            return TestConnectionResult::error(__('Invalid email or password', 'wp-sms'));
        }

        if ($result['code'] === 402) {
            return TestConnectionResult::error(__('App SID was not accepted by Unifonic', 'wp-sms'));
        }

        // Any 4xx other than auth failures means the credentials are valid and the
        // request was rejected for a non-auth reason (missing recipient, etc.).
        if ($result['code'] >= 200 && $result['code'] < 500) {
            return TestConnectionResult::ok(__('Connected — Unifonic credentials are valid', 'wp-sms'));
        }

        return TestConnectionResult::error(
            sprintf(__('Unexpected response from Unifonic (HTTP %d)', 'wp-sms'), $result['code']),
        );
    }

    private function authHeaders(string $email, string $password): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode($email . ':' . $password),
            'Content-Type'  => 'application/x-www-form-urlencoded',
            'Accept'        => 'application/json',
        ];
    }
}

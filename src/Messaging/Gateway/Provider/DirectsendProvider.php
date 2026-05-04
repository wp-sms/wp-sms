<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * DirectSend (directsend.co.kr) — Korea-only SMS gateway operated by
 * Samjung Data Service.
 *
 * Auth: `username` + `key` placed inside the JSON request body (not
 * headers). Sender numbers must be pre-registered in the dashboard
 * (Korean regulation 발신번호 사전등록제) and the calling server's IP
 * must be added to the dashboard allowlist or sends silently fail.
 *
 * Phone format: Korean local format. We strip a leading `+82` country
 * code and ensure the resulting number starts with `0`.
 *
 * Out of scope (deferred): Kakao AlimTalk / FriendTalk channels (no
 * `kakao` channel slug exists in v8 yet); DLR webhook (`리턴URL`) — the
 * payload field is documented but the full shape is only inside the
 * downloadable API zip; balance lookup — endpoint exists per release
 * notes but isn't surfaced in the public manual; test-connection — no
 * documented endpoint.
 */
class DirectsendProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_URL = 'https://directsend.co.kr/index.php/api_v2/sms_change_word';

    public function getId(): string
    {
        return 'directsend';
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
                    'description' => __('DirectSend account ID issued in the dashboard at directsend.co.kr.', 'wp-sms'),
                ],
                'key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('API key generated in the DirectSend dashboard alongside the account ID.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Pre-registered Korean sender number in local format (no country code, leading 0). Korean regulation 발신번호 사전등록제 requires every sender to be registered in the DirectSend dashboard before use.', 'wp-sms'),
                        'placeholder' => '0212345678',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = $this->getSharedConfig('username');
        $key      = $this->getSharedConfig('key');
        $from     = $this->getChannelConfig('sms', 'from');

        if (!$username || !$key || !$from) {
            return DeliveryResult::failed(__('DirectSend credentials not configured', 'wp-sms'));
        }

        $body = [
            'username' => $username,
            'key'      => $key,
            'sender'   => $from,
            'receiver' => [
                ['mobile' => $this->cleanKoreanNumber($message->getRecipient())],
            ],
            'message'  => $message->getBody(),
        ];

        $result = $this->httpPost(self::SEND_URL, [
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(sprintf('HTTP %d', $result['code']));
        }

        $data = json_decode($result['body'], true);
        $status = is_array($data) ? ($data['status'] ?? null) : null;

        if ($status === 0 || $status === '0' || $status === 1 || $status === '1') {
            return DeliveryResult::queued(null);
        }

        $errorMessage = is_array($data) ? ($data['message'] ?? null) : null;

        return DeliveryResult::failed(
            $errorMessage ?: sprintf('DirectSend send failed (status %s)', (string) $status),
        );
    }

    /**
     * Balance endpoint exists per DirectSend release notes but its path is
     * only documented inside the downloadable API zip — left null until a
     * credentialed test confirms it.
     */
    public function getCredit(): ?string
    {
        return null;
    }

    /**
     * Normalize a phone number to Korean local format: strip a leading
     * `+82` (or bare `82`) country code and prefix `0` when missing.
     *
     * Examples:
     *   +821012345678 → 01012345678
     *   821012345678  → 01012345678
     *   1012345678    → 01012345678
     *   01012345678   → 01012345678
     */
    private function cleanKoreanNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number);

        if ($digits === '') {
            return $digits;
        }

        if (str_starts_with($digits, '82')) {
            $digits = substr($digits, 2);
        }

        if (!str_starts_with($digits, '0')) {
            $digits = '0' . $digits;
        }

        return $digits;
    }
}

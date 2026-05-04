<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * EuroSMS (eurosms.com) — Slovak SMS gateway with operator-direct delivery
 * across Central Europe (SK/CZ/HU/AT) and worldwide partner routing.
 *
 * Targets the documented REST/JSON API v3 (https://as.eurosms.com/api/v3).
 * Auth uses an Integration ID (`iid`, format `1-XXXXXX` or `2-YYYYYY`) plus
 * a per-request HMAC-SHA1 signature over `$sender . $rcpt . $txt` keyed by
 * the Integration Key.
 *
 * Out of scope (deferred): bulk endpoints (/send/o2m, /send/m2m) — fanout is
 * handled by MessageDispatcher per-recipient; status callback — provisioned
 * provider-side, no API self-service and no native HMAC on the callback;
 * inbound; Viber (IM) — needs sender-registration UX WSMS doesn't yet expose.
 *
 * TODO(callback): wire SupportsStatusCallback once we agree on a shared-secret
 * token scheme — provider has no native HMAC.
 * TODO(viber): add im{} payload once WSMS exposes Viber as a channel.
 */
class EuroSmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://as.eurosms.com/api/v3';

    /** flgs bit: long SMS (n×153 GSM7 chars / n×67 unicode chars). */
    private const FLAG_LONG = 0x02;

    /** flgs bit: unicode body (diacritics / non-GSM7). 70 chars per segment. */
    private const FLAG_UNICODE = 0x04;

    public function getId(): string
    {
        return 'eurosms';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'integration_id' => [
                    'type'        => 'string',
                    'label'       => __('Integration ID', 'wp-sms'),
                    'required'    => true,
                    'placeholder' => '1-ABCDEF',
                    'description' => __('Your EuroSMS Integration ID (1-XXXXXX or 2-YYYYYY) from the customer portal.', 'wp-sms'),
                ],
                'integration_key' => [
                    'type'        => 'secret',
                    'label'       => __('Integration Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('The secret key paired with your Integration ID. Used to compute the request signature.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'YourBrand',
                        'description' => __('Alphanumeric sender (max 11 chars, A–Z, 0–9, dash/space/dot) or a full international number without leading + or 00.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $iid    = (string) $this->getSharedConfig('integration_id');
        $key    = (string) $this->getSharedConfig('integration_key');
        $sender = (string) $this->getChannelConfig('sms', 'from');

        if ($iid === '' || $key === '' || $sender === '') {
            return DeliveryResult::failed(__('EuroSMS credentials not configured', 'wp-sms'));
        }

        $rcpt = $this->normalizeRecipient($message->getRecipient());
        $text = $message->getBody();

        $body = [
            'iid'  => $iid,
            'sgn'  => hash_hmac('sha1', $sender . $rcpt . $text, $key),
            'rcpt' => $rcpt,
            'sndr' => $sender,
            'txt'  => $text,
            'flgs' => $this->computeFlags($text),
        ];

        $result = $this->httpPost(self::API_BASE . '/send/one', [
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        return $this->parseSendResponse($result);
    }

    public function testConnection(): TestConnectionResult
    {
        $iid    = (string) $this->getSharedConfig('integration_id');
        $key    = (string) $this->getSharedConfig('integration_key');
        $sender = (string) $this->getChannelConfig('sms', 'from');

        if ($iid === '' || $key === '') {
            return TestConnectionResult::error(__('Integration ID and Integration Key are required', 'wp-sms'));
        }

        // Sender is required by the API; fall back to a safe alphanumeric for the
        // dry-run validation when the user hasn't filled it in yet.
        if ($sender === '') {
            $sender = 'WSMS';
        }

        $rcpt = '421000000000';
        $text = 'WSMS connection test';

        $body = [
            'iid'  => $iid,
            'sgn'  => hash_hmac('sha1', $sender . $rcpt . $text, $key),
            'rcpt' => $rcpt,
            'sndr' => $sender,
            'txt'  => $text,
            'flgs' => 0,
        ];

        $result = $this->httpPost(self::API_BASE . '/test/one', [
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the EuroSMS API. Check your server\'s internet connection.', 'wp-sms'),
            );
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return TestConnectionResult::error(__('Invalid Integration ID or Integration Key', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] >= 200 && $result['code'] < 300 && is_array($data)) {
            $errCode = (string) ($data['err_code'] ?? '');
            if ($errCode === 'ENQUEUED') {
                return TestConnectionResult::ok(__('Connection successful', 'wp-sms'));
            }

            if ($this->isAuthError($data)) {
                return TestConnectionResult::error(__('Invalid Integration ID or Integration Key', 'wp-sms'));
            }

            return TestConnectionResult::error($this->formatErrorList($data));
        }

        return TestConnectionResult::error(
            sprintf(__('Unexpected response from EuroSMS (HTTP %d)', 'wp-sms'), $result['code']),
        );
    }

    private function parseSendResponse(array $result): DeliveryResult
    {
        $data = json_decode($result['body'], true);

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid EuroSMS credentials', 'wp-sms'));
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            $error = is_array($data) ? $this->formatErrorList($data) : '';
            return DeliveryResult::failed(
                $error !== '' ? $error : sprintf('HTTP %d', $result['code']),
            );
        }

        if (!is_array($data)) {
            return DeliveryResult::failed(__('Invalid response from EuroSMS', 'wp-sms'));
        }

        $errCode = (string) ($data['err_code'] ?? '');
        $uuids   = $data['uuid'] ?? [];

        if ($errCode === 'ENQUEUED' && is_array($uuids) && !empty($uuids)) {
            return DeliveryResult::sent((string) $uuids[0]);
        }

        return DeliveryResult::failed($this->formatErrorList($data));
    }

    /**
     * Bit 0x04 if the body contains characters outside basic ASCII (diacritics
     * force unicode encoding, 70 chars per segment). Bit 0x02 when the body
     * exceeds a single segment (160 ASCII or 70 unicode).
     */
    private function computeFlags(string $text): int
    {
        $flags = 0;
        $isUnicode = preg_match('/[^\x00-\x7F]/', $text) === 1;

        if ($isUnicode) {
            $flags |= self::FLAG_UNICODE;
        }

        $length = $isUnicode ? mb_strlen($text, 'UTF-8') : strlen($text);
        $segmentLimit = $isUnicode ? 70 : 160;

        if ($length > $segmentLimit) {
            $flags |= self::FLAG_LONG;
        }

        return $flags;
    }

    /**
     * Strip the leading `+` and any non-digit characters. EuroSMS expects a
     * full international number in digits-only form (e.g. `421903622237`);
     * SK/CZ short forms are auto-detected provider-side.
     */
    private function normalizeRecipient(string $recipient): string
    {
        return preg_replace('/\D+/', '', $recipient) ?? '';
    }

    private function isAuthError(array $data): bool
    {
        foreach ($this->collectErrorCodes($data) as $code) {
            if (in_array($code, ['WRONG_SIGNATURE', 'INVALID_IID', 'UNAUTHORIZED'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string[]
     */
    private function collectErrorCodes(array $data): array
    {
        $codes = [];
        if (!empty($data['err_code']) && is_string($data['err_code'])) {
            $codes[] = $data['err_code'];
        }
        foreach ($data['err_list'] ?? [] as $entry) {
            if (is_array($entry) && !empty($entry['err_code']) && is_string($entry['err_code'])) {
                $codes[] = $entry['err_code'];
            }
        }
        return $codes;
    }

    private function formatErrorList(array $data): string
    {
        $parts = [];
        foreach ($data['err_list'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $code = (string) ($entry['err_code'] ?? '');
            $desc = (string) ($entry['err_desc'] ?? '');
            if ($code !== '' && $desc !== '') {
                $parts[] = "{$code}: {$desc}";
            } elseif ($code !== '') {
                $parts[] = $code;
            } elseif ($desc !== '') {
                $parts[] = $desc;
            }
        }

        if (!empty($parts)) {
            return implode('; ', $parts);
        }

        $top = (string) ($data['err_code'] ?? '');
        $desc = (string) ($data['err_desc'] ?? '');
        if ($top !== '' && $desc !== '') {
            return "{$top}: {$desc}";
        }

        return $top !== '' ? $top : ($desc !== '' ? $desc : __('EuroSMS send failed', 'wp-sms'));
    }
}
